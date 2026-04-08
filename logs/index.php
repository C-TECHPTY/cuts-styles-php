<?php
/**
 * index.php - Archivo de seguridad para la carpeta logs
 * 
 * Este archivo evita que los usuarios accedan directamente a la carpeta logs
 * y redirige a la página principal del sistema.
 * 
 * @package Cuts & Styles
 * @version 1.0
 */

// Redirigir a la página principal
header("Location: ../index.php");
exit();
?>