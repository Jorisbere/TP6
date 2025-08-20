<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/lib/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// 🔐 Vérification de session
if (!isset($_SESSION['user_id'])) {
    die("Accès refusé. Veuillez vous connecter.");
}

// 🔍 Vérification de l'ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de déclaration invalide.");
}

$id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// 📥 Récupération sécurisée de la déclaration
$stmt = $pdo->prepare("SELECT * FROM declarations WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $user_id]);
$data = $stmt->fetch();

if (!$data) {
    die("Déclaration introuvable.");
}

// 🧾 Préparation du HTML
$html = "
<!DOCTYPE html>
<html lang='fr'>
<head>
  <meta charset='UTF-8'>
  <style>
    body {
      font-family: 'Arial', sans-serif;
      padding: 30px;
      color: #333;
    }
    h1 {
      text-align: center;
      color: #0078D7;
      margin-bottom: 30px;
    }
    .section {
      margin-bottom: 20px;
    }
    .row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
      font-size: 13pt;
    }
    .row .label {
      font-weight: bold;
      color: #555;
    }
    .row .value {
      text-align: right;
      color: #333;
    }
    .footer {
      margin-top: 40px;
      text-align: center;
      font-size: 10px;
      color: #888;
    }
    .box {
      border: 1px solid #ccc;
      padding: 15px;
      border-radius: 8px;
      background-color: #f9f9f9;
    }
  </style>
</head>
<body>
  <h1>Déclaration fiscale – " . ucfirst($data['profile']) . "</h1>

  <div class='box'>
    <div class='section'>
      <div class='row'>
        <div class='label'>Nom du déclarant :</div>
        <div class='value'>{$data['fullname']}</div>
      </div>
      <div class='row'>
        <div class='label'>Email :</div>
        <div class='value'>{$data['email']}</div>
      </div>
      <div class='row'>
        <div class='label'>Numéro fiscal :</div>
        <div class='value'>{$data['fiscal_id']}</div>
      </div>
    </div>

    <div class='section'>
      <div class='row'>
        <div class='label'>Année fiscale :</div>
        <div class='value'>{$data['year']}</div>
      </div>
      <div class='row'>
        <div class='label'>Date de déclaration :</div>
        <div class='value'>" . date('d/m/Y à H:i', strtotime($data['declaration_date'])) . "</div>
      </div>
      <div class='row'>
        <div class='label'>Revenu déclaré :</div>
        <div class='value'>" . number_format($data['income'], 0, ',', ' ') . " FCFA</div>
      </div>
      <div class='row'>
        <div class='label'>Dépenses :</div>
        <div class='value'>" . number_format($data['expenses'], 0, ',', ' ') . " FCFA</div>
      </div>
      <div class='row'>
        <div class='label'>Personnes à charge :</div>
        <div class='value'>{$data['dependents']}</div>
      </div>
      <div class='row'>
        <div class='label'>Impôt dû :</div>
        <div class='value'>" . number_format($data['tax_due'], 0, ',', ' ') . " FCFA</div>
      </div>
    </div>
  </div>

  <div class='qr'>
  <p><strong>📱 Vérification :</strong></p>
  <img class='qr-code' src='https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=https://fiscal-platform.com/declaration/{$data['id']}' alt='QR Code'>
  <p style='font-size:10px; color:#888;'>Scan pour consulter la déclaration en ligne</p>
</div>

<div class='signature' style='margin-top:20px; text-align:right;'>
  <p><strong>Signature électronique :</strong></p>
  <img class='signature-img' src='https://www.bing.com/th/id/OIP.2FPGtJFA5h3SAnMXCf0vvgHaEv?w=244&h=211&c=8&rs=1&qlt=90&o=6&dpr=1.5&pid=3.1&rm=2' width='30' alt='Signature'>
  <p style='font-size:10px; color:#888;'>Signé automatiquement par Fiscal Platform</p>
</div>


  <div class='footer'>
    Document généré automatiquement le " . date('d/m/Y à H:i') . "
  </div>
</body>
</html>
";

// ⚙️ Configuration DomPDF
$options = new Options();
$options->set('defaultFont', 'Arial');
$options->setIsRemoteEnabled(true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$canvas = $dompdf->getCanvas();
$canvas->set_opacity(0.1);
$canvas->text(150, 500, "Tax Document", "Arial", 50, [0, 0, 0]);


// 📎 Téléchargement
$filename = 'declaration_' . $data['profile'] . '_' . $data['year'] . '_' . date('Ymd_His') . '.pdf';
$dompdf->stream($filename, ["Attachment" => true]);
