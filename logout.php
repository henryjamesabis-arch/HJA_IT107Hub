<?php
define('APP_RUNNING', true);
require __DIR__ . '/php/bootstrap.php';
logout_session();            // destroys the server-side session
header('Location: login.php?out=1');
exit;
