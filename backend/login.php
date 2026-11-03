<?php
require_once __DIR__ . '/db.php';
start_session();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!valid_csrf($_POST['csrf_token'] ?? null)) $error = 'Sua sessão expirou. Atualize a página e tente novamente.';
    else {
        try {
            $email = strtolower(trim($_POST['email'] ?? ''));
            $stmt = db()->prepare('SELECT id,nome,email,senha_hash,tipo_acesso,setor FROM usuarios WHERE email=? AND ativo=1');
            $stmt->execute([$email]); $user = $stmt->fetch();
            if (!$user || !password_verify($_POST['password'] ?? '', $user['senha_hash'])) $error = 'E-mail ou senha inválidos.';
            else { session_regenerate_id(true); unset($user['senha_hash']); $_SESSION['user'] = $user; header('Location: ' . app_path('painel.html'), true, 303); exit; }
        } catch (Throwable $e) { $error = 'Não foi possível conectar ao banco de dados. Confira as variáveis de conexão do Supabase no Render.'; }
    }
}
function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
$html = file_get_contents(__DIR__ . '/../login.html');
$errorBox = $error ? '<div role="alert" class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700">' . h($error) . '</div>' : '';
$successBox = ($_GET['cadastro'] ?? '') === 'sucesso' ? '<div class="mb-4 p-3 rounded-lg bg-green-50 border border-green-200 text-sm text-green-700">Cadastro salvo. Entre com seu e-mail e senha.</div>' : '';
$loginAction = h(app_path('login.html'));
$html = str_replace('<form action="login.html" method="POST">', '<form action="' . $loginAction . '" method="POST"><input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">' . $successBox . $errorBox, $html);
$html = str_replace('href="cadastro.html"', 'href="' . h(app_path('cadastro.html')) . '"', $html);
$html = str_replace('<!--esqueceu senha-->', '<div class="text-center mt-5"><p class="text-xs text-slate-800">N&atilde;o tem login? <a href="' . h(app_path('cadastro.html')) . '" class="text-blue-600 font-bold hover:underline">Fa&ccedil;a seu cadastro.</a></p></div><!--esqueceu senha-->', $html);
echo $html;


