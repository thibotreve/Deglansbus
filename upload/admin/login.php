<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['gebruiker'] ?? '') === ADMIN_USER && ($_POST['wachtwoord'] ?? '') === ADMIN_PASSWORD) {
        $_SESSION['ingelogd'] = true;
        header('Location: index.php');
        exit;
    }
    $fout = true;
}

if (!empty($_SESSION['ingelogd'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aanmelden – De Glansbus Admin</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Inter, -apple-system, sans-serif; background: #f4f5f7; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .kaart { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 48px 40px; width: 100%; max-width: 380px; box-shadow: 0 4px 24px rgba(0,0,0,0.06); }
    .logo-blok { text-align: center; margin-bottom: 32px; }
    .logo-blok h1 { font-size: 1.2rem; font-weight: 800; color: #1a1c1e; letter-spacing: -0.5px; }
    .logo-blok p { font-size: 0.82rem; color: #9ca3af; margin-top: 4px; }
    label { display: block; font-size: 0.82rem; font-weight: 600; color: #374151; margin-bottom: 6px; }
    input { width: 100%; padding: 11px 14px; border: 1.5px solid #e5e7eb; border-radius: 8px; font-size: 0.95rem; color: #1a1c1e; outline: none; font-family: inherit; margin-bottom: 16px; }
    input:focus { border-color: #1a1c1e; }
    .fout { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; font-size: 0.82rem; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; }
    button { width: 100%; padding: 12px; background: #1a1c1e; color: #fff; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 600; cursor: pointer; font-family: inherit; }
    button:hover { opacity: 0.85; }
  </style>
</head>
<body>
  <div class="kaart">
    <div class="logo-blok">
      <h1>De Glansbus</h1>
      <p>Admin omgeving</p>
    </div>
    <form method="POST">
      <?php if (!empty($fout)): ?>
        <div class="fout">Ongeldige inloggegevens. Probeer opnieuw.</div>
      <?php endif; ?>
      <label for="gebruiker">Gebruikersnaam</label>
      <input type="text" id="gebruiker" name="gebruiker" placeholder="Gebruikersnaam" autofocus required>
      <label for="wachtwoord">Wachtwoord</label>
      <input type="password" id="wachtwoord" name="wachtwoord" placeholder="••••••••" required>
      <button type="submit">Aanmelden</button>
    </form>
  </div>
</body>
</html>
