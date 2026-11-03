# Ellenco Construcoes — Render + Supabase

## Banco de dados
1. Abra o projeto no Supabase.
2. Acesse SQL Editor e execute todo o arquivo `database.sql`.

## Render
Crie um Web Service conectado ao GitHub e escolha Docker. O Render usara o `Dockerfile` da raiz.

Configure em Render > Environment:
- `DB_HOST`: host PostgreSQL do Supabase (use Session Pooler se necessario)
- `DB_PORT`: porta informada pelo Supabase
- `DB_NAME`: normalmente `postgres`
- `DB_USER`: usuario informado pelo Supabase para essa conexao
- `DB_PASS`: senha do banco Supabase
- `DB_SSLMODE`: `require`

Nao coloque a senha do banco no GitHub.

## Observacoes importantes
- Os arquivos PHP passaram na verificacao de sintaxe `php -l`.
- O cadastro atualmente permite escolher o tipo `admin`. Para producao, isso deve ser restrito para impedir que qualquer visitante crie uma conta administrativa.
- Algumas informacoes do painel e da equipe ainda sao demonstrativas/estaticas no HTML.
