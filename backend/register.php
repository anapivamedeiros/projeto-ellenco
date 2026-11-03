<?php
require_once __DIR__ . '/db.php';
start_session();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $senha = $_POST['senha'] ?? '';
    $tipo = $_POST['tipoAcesso'] ?? 'usuario';
    $setor = $_POST['setor'] ?? '';
    if (!valid_csrf($_POST['csrf_token'] ?? null)) $error = 'Sua sessão expirou. Atualize a página e tente novamente.';
    elseif ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 8 || !in_array($tipo, ['usuario','admin'], true) || !in_array($setor, ['engenharia','administrativo','obras'], true)) $error = 'Confira os campos preenchidos e tente novamente. A senha precisa ter pelo menos 8 caracteres.';
    else {
        try {
            $stmt = db()->prepare('INSERT INTO usuarios (nome,email,senha_hash,tipo_acesso,setor) VALUES (?,?,?,?,?)');
            $stmt->execute([$nome,$email,password_hash($senha,PASSWORD_DEFAULT),$tipo,$setor]);
            header('Location: ' . app_path('login.html?cadastro=sucesso'), true, 303); exit;
        } catch (PDOException $e) { $error = $e->getCode() === '23505' ? 'Este e-mail já está cadastrado.' : 'Não foi possível salvar no banco de dados.'; }
    }
}
function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
$html = file_get_contents(__DIR__ . '/../cadastro.html');
$errorBox = $error ? '<div role="alert" class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700">' . h($error) . '</div>' : '';
$registerAction = h(app_path('cadastro.html'));
$html = str_replace('<form action="cadastro.html" method="POST">', '<form action="' . $registerAction . '" method="POST"><input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">' . $errorBox, $html);
$html = str_replace('href="login.html"', 'href="' . h(app_path('login.html')) . '"', $html);
$html = str_replace('id="senha"', 'id="senha" minlength="8" autocomplete="new-password"', $html);
echo $html;

