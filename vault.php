<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}
$userId = $_SESSION['user_id'];

// ---------- DB ----------
$pdo = new PDO("mysql:host=localhost;dbname=unilink_db;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// ---------- UPLOAD ----------
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['doc'])) {
    $file = $_FILES['doc'];
    $dir  = 'uploads/vault/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $allowed = ['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $max = 10*1024*1024; // 10 MB

    if ($file['error'] !== 0) $msg = ['error','Upload error'];
    elseif (!array_key_exists($ext,$allowed)) $msg = ['error','Only PDF/JPG/PNG/GIF'];
    elseif ($file['size'] > $max) $msg = ['error','Max 10 MB'];
    else {
        $name = uniqid('doc_',true).'.'.$ext;
        if (move_uploaded_file($file['tmp_name'],$dir.$name)) {
            $stmt = $pdo->prepare("INSERT INTO documents (user_id,filename,original_name,file_type,file_size) VALUES (?,?,?,?,?)");
            $stmt->execute([$userId,$name,$file['name'],$file['type'],$file['size']]);
            $msg = ['success','Uploaded: '.htmlspecialchars($file['name'])];
        } else $msg = ['error','Save failed'];
    }
    // AJAX response
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode(['msg'=>$msg]);
        exit;
    }
}

// ---------- DELETE ----------
if (isset($_GET['del'])) {
    $id = (int)$_GET['del'];
    $stmt = $pdo->prepare("SELECT filename FROM documents WHERE doc_id=? AND user_id=?");
    $stmt->execute([$id,$userId]);
    $f = $stmt->fetch();
    if ($f && unlink('uploads/vault/'.$f['filename'])) {
        $pdo->prepare("DELETE FROM documents WHERE doc_id=?")->execute([$id]);
    }
    header('Location: vault.php');
    exit;
}

// ---------- FETCH ALL ----------
$stmt = $pdo->prepare("SELECT * FROM documents WHERE user_id=? ORDER BY uploaded_at DESC");
$stmt->execute([$userId]);
$files = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>UniLink | Secure Vault</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Space+Grotesk:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ---------- 2025+ FUTURISTIC VARIABLES ---------- */
:root {
    --primary: #06b6d4;
    --primary-hover: #0e7490;
    --primary-glow: rgba(6, 182, 212, 0.4);
    --primary-light: rgba(6, 182, 212, 0.1);
    
    --secondary: #8b5cf6;
    --secondary-glow: rgba(139, 92, 246, 0.4);
    
    --accent: #f59e0b;
    --success: #10b981;
    --warning: #f59e0b;
    --error: #ef4444;
    
    --neon-blue: #00d4ff;
    --neon-purple: #a855f7;
    --neon-pink: #ec4899;
    
    --bg-body: #0a0f1c;
    --bg-card: #111827;
    --bg-glass: rgba(17, 24, 39, 0.6);
    --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
    --bg-hologram: linear-gradient(45deg, 
        rgba(6, 182, 212, 0.05) 0%, 
        rgba(139, 92, 246, 0.05) 25%, 
        rgba(236, 72, 153, 0.05) 50%, 
        rgba(6, 182, 212, 0.05) 75%, 
        rgba(139, 92, 246, 0.05) 100%);
    
    --text-main: #f1f5f9;
    --text-subtle: #94a3b8;
    --text-light: #64748b;
    
    --border: #1e293b;
    --border-glow: rgba(6, 182, 212, 0.3);
    --border-light: #334155;
    
    --shadow-sm: 0 4px 6px -1px rgba(0,0,0,0.3);
    --shadow-md: 0 10px 15px -3px rgba(0,0,0,0.4);
    --shadow-lg: 0 25px 50px -12px rgba(0,0,0,0.5);
    --shadow-neon: 0 0 20px var(--primary-glow);
    --shadow-hologram: 0 0 30px rgba(6, 182, 212, 0.2);
    
    --transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
    --transition-slow: all 0.6s cubic-bezier(0.25, 0.8, 0.25, 1);
    
    --blur-bg: blur(20px);
    --blur-glass: blur(40px);
}

/* Light Mode Variables */
:root:not(.dark) {
    --primary: #06b6d4;
    --primary-hover: #0e7490;
    --primary-glow: rgba(6, 182, 212, 0.2);
    --primary-light: rgba(6, 182, 212, 0.05);
    
    --secondary: #8b5cf6;
    --secondary-glow: rgba(139, 92, 246, 0.2);
    
    --bg-body: #f8fafc;
    --bg-card: #ffffff;
    --bg-glass: rgba(255, 255, 255, 0.8);
    --bg-gradient: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 50%, #f1f5f9 100%);
    --bg-hologram: linear-gradient(45deg, 
        rgba(6, 182, 212, 0.03) 0%, 
        rgba(139, 92, 246, 0.03) 25%, 
        rgba(236, 72, 153, 0.03) 50%, 
        rgba(6, 182, 212, 0.03) 75%, 
        rgba(139, 92, 246, 0.03) 100%);
    
    --text-main: #1e293b;
    --text-subtle: #64748b;
    --text-light: #94a3b8;
    
    --border: #e2e8f0;
    --border-glow: rgba(6, 182, 212, 0.2);
    --border-light: #f1f5f9;
    
    --shadow-sm: 0 4px 6px -1px rgba(0,0,0,0.1);
    --shadow-md: 0 10px 15px -3px rgba(0,0,0,0.1);
    --shadow-lg: 0 25px 50px -12px rgba(0,0,0,0.15);
    --shadow-neon: 0 0 15px var(--primary-glow);
    --shadow-hologram: 0 0 20px rgba(6, 182, 212, 0.1);
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: 'Inter', sans-serif;
    background: var(--bg-gradient);
    color: var(--text-main);
    min-height: 100vh;
    line-height: 1.6;
    transition: var(--transition);
    overflow-x: hidden;
    position: relative;
}

/* Holographic Background Effects */
body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: var(--bg-hologram);
    background-size: 400% 400%;
    animation: hologramShift 8s ease-in-out infinite;
    z-index: -2;
    opacity: 0.3;
}

@keyframes hologramShift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

/* Particle Background */
.particles {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: -1;
    pointer-events: none;
}

.particle {
    position: absolute;
    width: 2px;
    height: 2px;
    background: var(--primary);
    border-radius: 50%;
    animation: float 6s infinite ease-in-out;
    opacity: 0.3;
}

@keyframes float {
    0%, 100% { transform: translateY(0) translateX(0); opacity: 0.3; }
    50% { transform: translateY(-20px) translateX(10px); opacity: 0.6; }
}

/* ---------- LAYOUT ---------- */
.container {
    max-width: 1400px;
    margin: auto;
    padding: 0 1.5rem;
}

/* Futuristic Header */
.header {
    position: sticky;
    top: 0;
    z-index: 1000;
    background: var(--bg-glass);
    backdrop-filter: var(--blur-bg);
    border-bottom: 1px solid var(--border-glow);
    box-shadow: var(--shadow-sm);
}

:root.dark .header {
    background: linear-gradient(135deg, 
        rgba(17, 24, 39, 0.8) 0%, 
        rgba(17, 24, 39, 0.6) 100%);
}

:root:not(.dark) .header {
    background: linear-gradient(135deg, 
        rgba(255, 255, 255, 0.8) 0%, 
        rgba(255, 255, 255, 0.6) 100%);
    border-bottom: 1px solid var(--border);
}

.nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0;
}

.logo {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 1.5rem;
    font-weight: 700;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    text-shadow: 0 0 30px var(--primary-glow);
    position: relative;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.logo::after {
    content: '';
    position: absolute;
    bottom: -5px;
    left: 0;
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--primary), transparent);
    border-radius: 2px;
}

.logo-icon {
    font-size: 1.25rem;
    animation: pulseGlow 2s ease-in-out infinite alternate;
}

@keyframes pulseGlow {
    from { filter: drop-shadow(0 0 5px var(--primary-glow)); }
    to { filter: drop-shadow(0 0 15px var(--primary-glow)); }
}

.nav-right {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.theme-btn {
    background: var(--bg-glass);
    border: 1px solid var(--border-glow);
    color: var(--text-main);
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 10px;
    transition: var(--transition);
    backdrop-filter: var(--blur-bg);
    position: relative;
    overflow: hidden;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.theme-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    transition: left 0.5s;
}

.theme-btn:hover::before {
    left: 100%;
}

.theme-btn:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-neon);
    transform: translateY(-2px);
}

.theme-btn svg {
    width: 18px;
    height: 18px;
}

:root:not(.dark) .moon { display: none; }
:root.dark .sun { display: none; }

.dashboard-btn {
    background: linear-gradient(135deg, var(--primary), var(--primary-hover));
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 10px;
    font-weight: 600;
    text-decoration: none;
    transition: var(--transition);
    box-shadow: var(--shadow-md);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    position: relative;
    overflow: hidden;
    font-size: 0.875rem;
}

.dashboard-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.dashboard-btn:hover::before {
    left: 100%;
}

.dashboard-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* ---------- FUTURISTIC HERO ---------- */
.hero {
    padding: 6rem 0 4rem;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.hero::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, var(--primary-glow) 0%, transparent 70%);
    border-radius: 50%;
    z-index: -1;
    animation: pulse 4s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 0.3; transform: translate(-50%, -50%) scale(1); }
    50% { opacity: 0.6; transform: translate(-50%, -50%) scale(1.1); }
}

.hero h1 {
    font-size: clamp(3rem, 6vw, 5rem);
    font-weight: 800;
    line-height: 1.1;
    margin-bottom: 1.5rem;
    background: linear-gradient(135deg, var(--text-main), var(--primary), var(--secondary));
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    text-shadow: 0 0 30px var(--primary-glow);
    position: relative;
}

.hero p {
    font-size: 1.375rem;
    color: var(--text-subtle);
    max-width: 600px;
    margin: 0 auto 3rem;
    line-height: 1.6;
}

/* ---------- HOLOGRAPHIC UPLOAD ZONE ---------- */
.upload-section {
    margin: 4rem 0;
}

.upload-zone {
    background: var(--bg-glass);
    border: 2px dashed var(--border-glow);
    border-radius: 24px;
    padding: 4rem 3rem;
    text-align: center;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    backdrop-filter: var(--blur-bg);
    transition: var(--transition);
    box-shadow: var(--shadow-hologram);
}

:root:not(.dark) .upload-zone {
    border: 2px dashed var(--border);
}

.upload-zone::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, var(--primary-light), transparent);
    transition: left 0.6s;
}

.upload-zone:hover::before {
    left: 100%;
}

.upload-zone:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-neon);
    transform: translateY(-5px);
}

.upload-zone.dragover {
    background: var(--primary-light);
    border-color: var(--primary);
    box-shadow: var(--shadow-neon);
}

.upload-zone input {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
}

.upload-icon {
    font-size: 4rem;
    color: var(--primary);
    margin-bottom: 2rem;
    filter: drop-shadow(0 0 20px var(--primary-glow));
    animation: floatIcon 3s ease-in-out infinite;
}

@keyframes floatIcon {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

.upload-text {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: var(--text-main);
}

.upload-subtext {
    color: var(--text-subtle);
    font-size: 1rem;
}

.progress {
    margin-top: 2rem;
    display: none;
}

.bar {
    height: 6px;
    background: var(--border);
    border-radius: 3px;
    overflow: hidden;
    position: relative;
}

.bar::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    border-radius: 3px;
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s;
}

.fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    border-radius: 3px;
    width: 0;
    transition: width 0.3s;
    position: relative;
    overflow: hidden;
}

.fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% { left: -100%; }
    100% { left: 100%; }
}

/* ---------- HOLOGRAPHIC GRID ---------- */
.files-section {
    margin: 4rem 0;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 3rem;
}

.section-title {
    font-size: 2rem;
    font-weight: 700;
    background: linear-gradient(135deg, var(--text-main), var(--primary));
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.section-title::before {
    content: '';
    width: 4px;
    height: 30px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border-radius: 2px;
}

.file-count {
    background: var(--primary-light);
    color: var(--primary);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.875rem;
    border: 1px solid var(--border-glow);
}

/* Futuristic Grid */
.hologrid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 2rem;
}

.hologram-card {
    background: var(--bg-glass);
    border: 1px solid var(--border-glow);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: var(--shadow-md);
    transition: var(--transition);
    backdrop-filter: var(--blur-bg);
    position: relative;
    cursor: pointer;
}

:root:not(.dark) .hologram-card {
    border: 1px solid var(--border);
}

.hologram-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, 
        rgba(6, 182, 212, 0.05) 0%, 
        rgba(139, 92, 246, 0.05) 100%);
    opacity: 0;
    transition: var(--transition);
}

.hologram-card:hover::before {
    opacity: 1;
}

.hologram-card:hover {
    transform: translateY(-10px) scale(1.02);
    box-shadow: var(--shadow-neon);
    border-color: var(--primary);
}

.file-preview {
    width: 100%;
    height: 200px;
    position: relative;
    overflow: hidden;
    background: var(--bg-card);
}

.file-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: var(--transition);
}

.hologram-card:hover .file-preview img {
    transform: scale(1.1);
}

.file-icon {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--bg-card), var(--border));
    color: var(--primary);
    font-size: 3rem;
}

:root:not(.dark) .file-icon {
    background: linear-gradient(135deg, #f8fafc, #e2e8f0);
}

.file-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: var(--bg-glass);
    color: var(--text-main);
    padding: 0.5rem 1rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    backdrop-filter: var(--blur-bg);
    border: 1px solid var(--border-glow);
}

.file-content {
    padding: 1.5rem;
}

.file-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    gap: 1rem;
}

.file-name {
    font-weight: 600;
    color: var(--text-main);
    line-height: 1.4;
    word-break: break-word;
    flex: 1;
}

.file-size {
    color: var(--primary);
    font-weight: 700;
    font-size: 0.875rem;
    white-space: nowrap;
}

.file-meta {
    color: var(--text-subtle);
    font-size: 0.875rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.file-actions {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 0.5rem;
}

.holo-btn {
    padding: 0.75rem;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    position: relative;
    overflow: hidden;
}

.holo-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.3s;
}

.holo-btn:hover::before {
    left: 100%;
}

.holo-btn.view {
    background: linear-gradient(135deg, var(--primary), var(--primary-hover));
    color: white;
}

.holo-btn.download {
    background: var(--bg-glass);
    color: var(--text-main);
    border: 1px solid var(--border-glow);
}

.holo-btn.delete {
    background: rgba(239, 68, 68, 0.1);
    color: var(--error);
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.holo-btn:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-sm);
}

.holo-btn.view:hover {
    box-shadow: 0 4px 12px var(--primary-glow);
}

.holo-btn.delete:hover {
    background: var(--error);
    color: white;
}

/* Empty State */
.empty-vault {
    text-align: center;
    padding: 6rem 2rem;
    color: var(--text-subtle);
}

.empty-icon {
    font-size: 5rem;
    margin-bottom: 2rem;
    opacity: 0.5;
    filter: drop-shadow(0 0 20px var(--primary-glow));
    animation: pulseGlow 2s ease-in-out infinite;
}

.empty-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: var(--text-main);
}

.empty-description {
    font-size: 1.125rem;
    max-width: 400px;
    margin: 0 auto;
}

/* ---------- HOLOGRAPHIC TOAST ---------- */
.toast {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    padding: 1.25rem 1.5rem;
    border-radius: 16px;
    color: white;
    font-weight: 600;
    box-shadow: var(--shadow-lg);
    z-index: 2000;
    opacity: 0;
    transform: translateY(20px) scale(0.9);
    transition: var(--transition);
    backdrop-filter: var(--blur-bg);
    border: 1px solid var(--border-glow);
    max-width: 400px;
}

.toast.show {
    opacity: 1;
    transform: translateY(0) scale(1);
}

.toast.success {
    background: linear-gradient(135deg, var(--success), #059669);
}

.toast.error {
    background: linear-gradient(135deg, var(--error), #dc2626);
}

.toast-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.toast-icon {
    font-size: 1.25rem;
}

/* ---------- HOLOGRAPHIC MODAL ---------- */
.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(10, 15, 28, 0.8);
    backdrop-filter: var(--blur-glass);
    z-index: 3000;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.modal.active {
    display: flex;
    animation: modalAppear 0.3s ease;
}

@keyframes modalAppear {
    from { 
        opacity: 0; 
        backdrop-filter: blur(0px);
    }
    to { 
        opacity: 1; 
        backdrop-filter: var(--blur-glass);
    }
}

.modal-holo {
    background: var(--bg-glass);
    border-radius: 24px;
    padding: 2.5rem;
    max-width: 500px;
    width: 100%;
    box-shadow: var(--shadow-neon);
    border: 1px solid var(--border-glow);
    backdrop-filter: var(--blur-bg);
    position: relative;
    overflow: hidden;
}

:root:not(.dark) .modal-holo {
    border: 1px solid var(--border);
}

.modal-holo::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
}

.modal-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: var(--text-main);
}

.modal-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

.holo-btn-lg {
    flex: 1;
    padding: 1rem 1.5rem;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.holo-btn-lg.cancel {
    background: var(--bg-glass);
    color: var(--text-main);
    border: 1px solid var(--border-glow);
}

.holo-btn-lg.confirm {
    background: linear-gradient(135deg, var(--error), #dc2626);
    color: white;
}

.holo-btn-lg:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
}

/* ---------- FOOTER ---------- */
.footer {
    padding: 3rem 0;
    text-align: center;
    color: var(--text-subtle);
    border-top: 1px solid var(--border);
    margin-top: 4rem;
    background: var(--bg-glass);
    backdrop-filter: var(--blur-bg);
}

:root:not(.dark) .footer {
    background: var(--bg-glass);
}

/* ---------- RESPONSIVE ---------- */
@media (max-width: 768px) {
    .container {
        padding: 0 1rem;
    }
    
    .nav {
        flex-wrap: wrap;
        gap: 1rem;
        padding: 0.75rem 0;
    }
    
    .nav-right {
        width: 100%;
        justify-content: space-between;
        gap: 0.5rem;
    }
    
    .logo {
        font-size: 1.25rem;
    }
    
    .logo-icon {
        font-size: 1rem;
    }
    
    .theme-btn {
        width: 36px;
        height: 36px;
        padding: 0.4rem;
    }
    
    .theme-btn svg {
        width: 16px;
        height: 16px;
    }
    
    .dashboard-btn {
        padding: 0.4rem 0.75rem;
        font-size: 0.8rem;
    }
    
    .dashboard-btn span {
        display: none;
    }
    
    .hero {
        padding: 4rem 0 2rem;
    }
    
    .upload-zone {
        padding: 3rem 1.5rem;
    }
    
    .hologrid {
        grid-template-columns: 1fr;
    }
    
    .file-actions {
        grid-template-columns: 1fr;
    }
    
    .section-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
}

@media (max-width: 480px) {
    .nav {
        padding: 0.5rem 0;
    }
    
    .logo {
        font-size: 1.125rem;
    }
    
    .theme-btn {
        width: 32px;
        height: 32px;
    }
    
    .dashboard-btn {
        padding: 0.35rem 0.5rem;
    }
    
    .hero h1 {
        font-size: 2.5rem;
    }
    
    .upload-icon {
        font-size: 3rem;
    }
    
    .upload-text {
        font-size: 1.25rem;
    }
    
    .modal-holo {
        padding: 2rem 1.5rem;
    }
    
    .modal-actions {
        flex-direction: column;
    }
}
</style>
</head>
<body>

<!-- Particle Background -->
<div class="particles" id="particles"></div>

<!-- ==================== HEADER ==================== -->
<header class="header">
    <div class="container nav">
        <a href="dashboard.php" class="logo">
            <i class="fas fa-shield-alt logo-icon"></i>
            UniLink Vault
        </a>
        <div class="nav-right">
            <button class="theme-btn" id="themeToggle">
                <svg class="sun" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <svg class="moon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
            </button>
            <a href="dashboard.php" class="dashboard-btn">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
        </div>
    </div>
</header>

<main class="container">
    <!-- ==================== HERO ==================== -->
    <section class="hero">
        <h1>Secure Digital Vault</h1>
        <p>Your documents are protected with military-grade encryption and holographic security protocols.</p>
    </section>

    <!-- ==================== UPLOAD ZONE ==================== -->
    <section class="upload-section">
        <form id="uploadForm" enctype="multipart/form-data">
            <div class="upload-zone" id="dropZone">
                <input type="file" name="doc" accept=".pdf,.jpg,.jpeg,.png,.gif" required>
                <div class="upload-icon">
                    <i class="fas fa-cloud-upload-alt"></i>
                </div>
                <div class="upload-text">Quantum Secure Upload</div>
                <div class="upload-subtext">Drag & drop or click to upload files (PDF, JPG, PNG, GIF - Max 10MB)</div>
                <div class="progress" id="prog">
                    <div class="bar">
                        <div class="fill" id="fill"></div>
                    </div>
                </div>
            </div>
        </form>
    </section>

    <!-- ==================== FILES GRID ==================== -->
    <section class="files-section">
        <div class="section-header">
            <h2 class="section-title">Protected Files</h2>
            <div class="file-count"><?= count($files) ?> files secured</div>
        </div>

        <div class="hologrid" id="filesGrid">
            <?php if (empty($files)): ?>
                <div class="empty-vault">
                    <div class="empty-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h3 class="empty-title">Vault is Empty</h3>
                    <p class="empty-description">Upload your first file to experience quantum-level security and holographic storage.</p>
                </div>
            <?php else: foreach ($files as $f):
                $ext = pathinfo($f['filename'],PATHINFO_EXTENSION);
                $icon = $ext==='pdf' ? 'fa-file-pdf' : 'fa-file-image';
                $size = $f['file_size']<1024*1024 ? round($f['file_size']/1024,1).' KB' : round($f['file_size']/(1024*1024),1).' MB';
                $path = "/unilink/uploads/vault/{$f['filename']}";
                $isImg = in_array($f['file_type'],['image/jpeg','image/png','image/gif']);
            ?>
                <div class="hologram-card">
                    <div class="file-preview">
                        <?php if ($isImg): ?>
                            <img src="<?= $path ?>" alt="<?= htmlspecialchars($f['original_name']) ?>">
                        <?php else: ?>
                            <div class="file-icon">
                                <i class="fas <?= $icon ?>"></i>
                            </div>
                        <?php endif; ?>
                        <div class="file-badge"><?= strtoupper($ext) ?></div>
                    </div>
                    <div class="file-content">
                        <div class="file-header">
                            <div class="file-name"><?= htmlspecialchars($f['original_name']) ?></div>
                            <div class="file-size"><?= $size ?></div>
                        </div>
                        <div class="file-meta">
                            <i class="fas fa-calendar"></i>
                            <?= date('M j, Y',strtotime($f['uploaded_at'])) ?>
                        </div>
                        <div class="file-actions">
                            <a href="<?= $path ?>" target="_blank" class="holo-btn view">
                                <i class="fas fa-eye"></i>
                                View
                            </a>
                            <a href="<?= $path ?>" download="<?= $f['original_name'] ?>" class="holo-btn download">
                                <i class="fas fa-download"></i>
                                Download
                            </a>
                            <a href="?del=<?= $f['doc_id'] ?>" class="holo-btn delete" onclick="return confirmDelete(<?= $f['doc_id'] ?>)">
                                <i class="fas fa-trash"></i>
                                Delete
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </section>
</main>

<!-- ==================== HOLOGRAPHIC TOAST ==================== -->
<div id="toast" class="toast">
    <div class="toast-content">
        <i class="fas toast-icon"></i>
        <span class="toast-message"></span>
    </div>
</div>

<!-- ==================== HOLOGRAPHIC DELETE MODAL ==================== -->
<div id="delModal" class="modal">
    <div class="modal-holo">
        <h3 class="modal-title">Quantum Deletion Protocol</h3>
        <p>This action will permanently erase the file from all storage clusters. This process cannot be reversed.</p>
        <div class="modal-actions">
            <button class="holo-btn-lg cancel" onclick="closeDel()">Cancel Operation</button>
            <button class="holo-btn-lg confirm" id="confirmDel">Initiate Deletion</button>
        </div>
    </div>
</div>

<footer class="footer">
    <div class="container">© 2025 UniLink | HICKS BOZON404 |999</div>
</footer>

<script>
// Generate particles
function createParticles() {
    const particles = document.getElementById('particles');
    const particleCount = 50;
    
    for (let i = 0; i < particleCount; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.left = Math.random() * 100 + 'vw';
        particle.style.top = Math.random() * 100 + 'vh';
        particle.style.animationDelay = Math.random() * 6 + 's';
        particle.style.animationDuration = (3 + Math.random() * 4) + 's';
        particles.appendChild(particle);
    }
}

// ---------- THEME ----------
const html = document.documentElement;
const themeBtn = document.getElementById('themeToggle');

// Initialize theme
function initTheme() {
    const storedTheme = localStorage.getItem('theme');
    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    
    if (storedTheme === 'dark' || (!storedTheme && systemPrefersDark)) {
        html.classList.add('dark');
    } else {
        html.classList.remove('dark');
    }
}

function toggleTheme() {
    if (html.classList.contains('dark')) {
        html.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    } else {
        html.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    }
}

// ---------- UPLOAD ----------
const drop = document.getElementById('dropZone');
const input = drop.querySelector('input');
const prog = document.getElementById('prog');
const fill = document.getElementById('fill');
const toast = document.getElementById('toast');
let delId = 0;

['dragenter','dragover'].forEach(e=>drop.addEventListener(e,()=>drop.classList.add('dragover')));
['dragleave','drop'].forEach(e=>drop.addEventListener(e,()=>drop.classList.remove('dragover')));

drop.addEventListener('drop',e=>{
    e.preventDefault();
    const f = e.dataTransfer.files[0];
    if(f){ 
        const dt=new DataTransfer(); 
        dt.items.add(f); 
        input.files=dt.files; 
        upload(); 
    }
});

input.addEventListener('change',upload);

function upload(){
    if(!input.files[0]) return;
    prog.style.display='block'; 
    fill.style.width='0%';
    
    const fd = new FormData();
    fd.append('doc',input.files[0]);

    const xhr = new XMLHttpRequest();
    xhr.open('POST','',true);
    xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');

    xhr.upload.onprogress = e=>{ 
        if(e.lengthComputable) {
            const percent = (e.loaded/e.total*100);
            fill.style.width = percent + '%';
            
            // Add pulsing effect during upload
            if (percent < 100) {
                fill.style.animation = 'pulse 1s infinite';
            }
        }
    };
    
    xhr.onload = ()=>{
        fill.style.animation = 'none';
        const res = JSON.parse(xhr.responseText);
        showToast(res.msg[0],res.msg[1]);
        if(res.msg[0]==='success') {
            setTimeout(()=>location.reload(),1500);
        } else {
            prog.style.display='none';
        }
    };
    
    xhr.send(fd);
}

// ---------- HOLOGRAPHIC TOAST ----------
function showToast(type,text){
    const toastIcon = toast.querySelector('.toast-icon');
    const toastMessage = toast.querySelector('.toast-message');
    
    toastMessage.textContent = text;
    toast.className = 'toast ' + type + ' show';
    
    if (type === 'success') {
        toastIcon.className = 'fas fa-check-circle toast-icon';
    } else {
        toastIcon.className = 'fas fa-exclamation-triangle toast-icon';
    }
    
    setTimeout(()=>{
        toast.classList.remove('show');
    },4000);
}

// ---------- HOLOGRAPHIC DELETE MODAL ----------
function confirmDelete(id){
    delId = id;
    document.getElementById('delModal').classList.add('active');
    return false;
}

function closeDel(){ 
    document.getElementById('delModal').classList.remove('active'); 
}

document.getElementById('confirmDel').onclick = ()=>{ 
    location.href='?del='+delId; 
};

// Add holographic effects to cards on hover
document.querySelectorAll('.hologram-card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-10px) scale(1.02)';
        this.style.boxShadow = '0 0 30px rgba(6, 182, 212, 0.4)';
    });
    
    card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0) scale(1)';
        this.style.boxShadow = 'var(--shadow-md)';
    });
});

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    createParticles();
    initTheme();
    
    // Add click event to theme button
    if (themeBtn) {
        themeBtn.addEventListener('click', toggleTheme);
    }
    
    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
        if (!localStorage.getItem('theme')) {
            initTheme();
        }
    });
    
    // Add loading animation to empty state icon
    const emptyIcon = document.querySelector('.empty-icon');
    if (emptyIcon) {
        emptyIcon.style.animation = 'pulseGlow 2s ease-in-out infinite';
    }
    
    // Add interactive effects to upload zone
    const uploadZone = document.getElementById('dropZone');
    uploadZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.style.transform = 'scale(1.02)';
    });
    
    uploadZone.addEventListener('dragleave', function() {
        this.style.transform = 'scale(1)';
    });
    
    uploadZone.addEventListener('drop', function(e) {
        e.preventDefault();
        this.style.transform = 'scale(1)';
    });
});

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + U to focus upload
    if ((e.ctrlKey || e.metaKey) && e.key === 'u') {
        e.preventDefault();
        document.getElementById('dropZone').click();
    }
});
</script>

</body>
</html>