<?php
/**
 * Conexão — constantes lidas das variáveis de ambiente do Render.
 *
 * Existe separado de config.php de propósito: config.php é a PÁGINA de
 * Configurações do app. O db.php inclui ESTE arquivo.
 */
define('DB_HOST',    getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT',    getenv('DB_PORT') ?: '5432');
define('DB_NAME',    getenv('DB_NAME') ?: 'postgres');
define('DB_USER',    getenv('DB_USER') ?: 'postgres');
define('DB_PASS',    getenv('DB_PASS') ?: '');
define('DB_SSLMODE', getenv('DB_SSLMODE') ?: 'require');
