<?php
// config/mercadopago.php

// Credenciales de Mercado Pago (MPOS Global)
// Obtén tus credenciales en: https://www.mercadopago.com.mx/developers/panel

// Modo sandbox (pruebas) o producción
define('MP_MODO', 'sandbox'); // 'sandbox' o 'production'

// Credenciales de prueba (sandbox)
define('MP_PUBLIC_KEY_SANDBOX', 'TEST-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');
define('MP_ACCESS_TOKEN_SANDBOX', 'TEST-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');

// Credenciales de producción
define('MP_PUBLIC_KEY_PROD', 'APP_USR-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');
define('MP_ACCESS_TOKEN_PROD', 'APP_USR-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');

// Seleccionar según modo
if(MP_MODO == 'sandbox') {
    define('MP_PUBLIC_KEY', MP_PUBLIC_KEY_SANDBOX);
    define('MP_ACCESS_TOKEN', MP_ACCESS_TOKEN_SANDBOX);
} else {
    define('MP_PUBLIC_KEY', MP_PUBLIC_KEY_PROD);
    define('MP_ACCESS_TOKEN', MP_ACCESS_TOKEN_PROD);
}

// URL de retorno después del pago
define('MP_RETURN_URL', BASE_URL . 'confirmar_pago.php');
define('MP_WEBHOOK_URL', BASE_URL . 'webhook_mp.php');
?>