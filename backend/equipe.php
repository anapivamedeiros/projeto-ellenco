<?php
require_once __DIR__ . '/db.php';
$user = require_login();
$admin = is_admin($user);
$pdo = db();

$stmt = $pdo->prepare(
    'SELECT u.id, u.nome, u.email, u.tipo_acesso,
            COUNT(DISTINCT m.id) AS total_metas,
            COUNT(DISTINCT CASE WHEN c.concluida = 1 THEN m.id END) AS metas_concluidas,
            AVG(m.progresso_percentual) AS progresso_setor
     FROM usuarios u
     LEFT JOIN metas m ON m.setor = u.setor
     LEFT JOIN meta_checklist c ON c.meta_id = m.id AND c.usuario_id = u.id
     WHERE u.ativo = 1 AND u.setor = ?
     GROUP BY u.id, u.nome, u.email, u.tipo_acesso
     ORDER BY u.nome'
);
$stmt->execute([$user['setor']]);
$members = $stmt->fetchAll();

$now = new DateTimeImmutable('now');
$weekStart = (new DateTimeImmutable('today'))->modify('monday this week');
$weekEnd = $weekStart->modify('+7 days');
$eventsStmt = $pdo->prepare(
    'SELECT titulo, inicio, categoria
     FROM eventos
     WHERE setor = ? AND inicio >= ? AND inicio < ?
     ORDER BY inicio
     LIMIT 6'
);
$eventsStmt->execute([
    $user['setor'],
    $now->format('Y-m-d H:i:s'),
    $weekEnd->format('Y-m-d H:i:s')
]);
$weeklyEvents = $eventsStmt->fetchAll();

function team_h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$memberRows = '';
foreach ($members as $member) {
    $parts = preg_split('/\s+/', trim($member['nome']));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }

    if ($member['tipo_acesso'] === 'admin') {
        $function = 'Administradora';
        $progress = (float) ($member['progresso_setor'] ?? 0);
    } else {
        $function = 'Membro da equipe';
        $progress = (int) $member['total_metas'] > 0
            ? ((int) $member['metas_concluidas'] / (int) $member['total_metas']) * 100
            : 0;
    }

    $progress = max(0, min(100, (int) round($progress)));
    $memberRows .= '<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-center py-4 border-b border-slate-200 last:border-b-0">'
        . '<div class="flex items-center gap-3 min-w-0"><div class="flex shrink-0 bg-blue-50 text-blue-900 rounded-full w-9 h-9 items-center justify-center font-bold text-xs">' . team_h($initials) . '</div>'
        . '<div class="min-w-0"><p class="font-semibold text-sm truncate">' . team_h($member['nome']) . '</p><p class="text-xs text-gray-500 truncate">' . team_h($user['setor'] ? sector_label($user['setor']) : '') . '</p></div></div>'
        . '<p class="text-sm text-gray-600">' . team_h($function) . '</p>'

        . '</div>';
}

if ($memberRows === '') {
    $memberRows = '<p class="py-5 text-sm text-gray-500">Ainda não há membros ativos neste setor.</p>';
}

$html = file_get_contents(__DIR__ . '/../equipe.html');

$categoryLabels = [
    'reuniao' => 'Reunião',
    'fiscalizacao' => 'Fiscalização',
    'manutencao' => 'Manutenção',
    'eventos' => 'Eventos', 'outros' => 'Outros'
];
$activityCards = '';
foreach ($weeklyEvents as $event) {
    $eventDate = new DateTimeImmutable($event['inicio']);
    $category = $categoryLabels[$event['categoria']] ?? 'Outros';
    $activityCards .= '<article class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">'
        . '<div class="shrink-0 rounded-lg bg-blue-50 px-3 py-2 text-center text-blue-900"><span class="block text-sm font-bold">' . $eventDate->format('d/m') . '</span><span class="block text-xs">' . $eventDate->format('H:i') . '</span></div>'
        . '<div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-800">' . team_h($event['titulo']) . '</p><p class="text-xs text-slate-500">' . team_h($category) . '</p></div>'
        . '</article>';
}
if ($activityCards === '') {
    $activityCards = '<p class="text-sm text-gray-500">Nenhum evento futuro programado para esta semana.</p>';
}

$activityTitle = strpos($html, '<h3 class="font-bold text-lg">Próximas Atividades</h3>');
if ($activityTitle !== false) {
    $containerStart = strpos($html, '<div class="space-y-5">', $activityTitle);
    if ($containerStart !== false) {
        $contentStart = strpos($html, '>', $containerStart) + 1;
        $containerEnd = strpos($html, '</div>', $contentStart);
        if ($containerEnd !== false) {
            $html = substr_replace($html, $activityCards, $contentStart, $containerEnd - $contentStart);
        }
    }
    $activityArea = substr($html, $activityTitle);
    $activityArea = preg_replace(
        '~<a href="#" class="text-blue-800 text-xs font-semibold">~',
        '<a href="calendario.html" class="text-blue-800 text-xs font-semibold">',
        $activityArea,
        1
    );
    $html = substr_replace($html, $activityArea, $activityTitle);
}

$cardStart = strpos($html, '<div class="xl:col-span-2 bg-white border rounded-xl p-5">');
$cardEnd = false;
if ($cardStart !== false) {
    preg_match_all('~</?div\b[^>]*>~i', substr($html, $cardStart), $tokens, PREG_OFFSET_CAPTURE);
    $depth = 0;
    foreach ($tokens[0] as $entry) {
        if (stripos($entry[0], '</div') === 0) {
            $depth--;
        } else {
            $depth++;
        }
        if ($depth === 0) {
            $cardEnd = $cardStart + $entry[1] + strlen($entry[0]);
            break;
        }
    }
}

if ($cardEnd !== false) {
    $replacement = '<div class="xl:col-span-2 bg-white border rounded-xl p-5">'
        . '<h3 class="font-bold text-lg mb-4">Membros da Equipe (' . count($members) . ')</h3>'
        . '<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs font-bold text-gray-500 border-b border-slate-200 pb-3">'
        . '<span>Nome</span><span>Função</span></div>'
        . $memberRows . '</div>';
    $html = substr_replace($html, $replacement, $cardStart, $cardEnd - $cardStart);
}

$html = preg_replace_callback('~<p class="font-semibold text-sm">[^<]*</p>~', function () use ($user) {
    return '<p class="font-semibold text-sm">' . team_h($user['nome']) . '</p>';
}, $html, 1);
$html = preg_replace_callback('~<p class="text-xs text-gray-500">Administradora</p>~', function () use ($admin) {
    return '<p class="text-xs text-gray-500">' . ($admin ? 'Administradora' : 'Usuária comum') . '</p>';
}, $html, 1);

if ($admin) {
    $html = str_replace(
        'href="#" class="bg-blue-800 text-white text-center px-5 py-3 rounded-lg font-semibold hover:bg-blue-900"',
        'href="cadastro.html" class="bg-blue-800 text-white text-center px-5 py-3 rounded-lg font-semibold hover:bg-blue-900"',
        $html
    );
} else {
    $html = preg_replace('~<a href="#" class="bg-blue-800 text-white text-center px-5 py-3 rounded-lg font-semibold hover:bg-blue-900">.*?\+ Adicionar membro.*?</a>~s', '', $html, 1);
}

echo $html;