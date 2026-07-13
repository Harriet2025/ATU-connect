<?php
/**
 * One-off CLI helper: run with XAMPP's php.exe to get a real bcrypt hash for
 * the seed data password, then paste it into seed.sql before importing.
 *
 * Usage (from XAMPP's php folder or with php in PATH):
 *   php generate_password_hash.php Password123!
 */

$password = $argv[1] ?? 'Password123!';
echo password_hash($password, PASSWORD_DEFAULT) . PHP_EOL;
