<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (empty($_SESSION['ingelogd'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Niet ingelogd']);
    exit;
}

function laadLeads() {
    if (!file_exists(LEADS_FILE)) return [];
    return json_decode(file_get_contents(LEADS_FILE), true) ?? [];
}

function slaLeadsOp($leads) {
    file_put_contents(LEADS_FILE, json_encode($leads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$method = $_SERVER['REQUEST_METHOD'];
$path   = trim($_GET['pad'] ?? '', '/');

// GET leads
if ($method === 'GET' && $path === 'leads') {
    echo json_encode(laadLeads());
    exit;
}

// POST leads/{id}/gelezen
if ($method === 'POST' && preg_match('/^leads\/(\d+)\/gelezen$/', $path, $m)) {
    $id = (int)$m[1];
    $leads = laadLeads();
    foreach ($leads as &$l) {
        if ($l['id'] === $id) $l['gelezen'] = true;
    }
    slaLeadsOp($leads);
    echo json_encode(['success' => true]);
    exit;
}

// DELETE leads/{id}
if ($method === 'DELETE' && preg_match('/^leads\/(\d+)$/', $path, $m)) {
    $id = (int)$m[1];
    $leads = array_values(array_filter(laadLeads(), fn($l) => $l['id'] !== $id));
    slaLeadsOp($leads);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Niet gevonden']);
