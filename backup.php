<?php
include 'auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Backup - Ummul Bannin Madrasah</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --emerald: #14532d; --parchment: #faf6ee; --gold: #b8860b;
        --ink: #2b2b2b; --sage: #e3ede3; --sage-border: #cddccd; --white: #ffffff;
    }
    * { box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background-color: var(--parchment); color: var(--ink); margin: 0; padding: 30px; }
    .container { max-width: 650px; margin: 0 auto; background: var(--white); padding: 30px 35px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
    h1 { font-family: 'Amiri', serif; color: var(--emerald); font-size: 24px; margin-bottom: 5px; }
    .subtitle { color: #666; margin-bottom: 25px; font-size: 14px; }
    .back-link { font-size: 13px; margin-bottom: 20px; display: inline-block; color: var(--emerald); }
    .warning-box {
        background-color: #fff8e6; border: 1px solid #ffe8a1; color: #856404;
        padding: 16px 18px; border-radius: 8px; margin-bottom: 25px; font-size: 14px;
    }
    .steps { padding-left: 20px; }
    .steps li { margin-bottom: 12px; line-height: 1.5; font-size: 14px; }
    .steps code {
        background-color: var(--sage); padding: 2px 6px; border-radius: 4px; font-size: 13px;
    }
    .btn {
        display: inline-block; margin-top: 20px; padding: 12px 20px;
        background-color: var(--emerald); color: white; border-radius: 6px;
        text-decoration: none; font-weight: 600; font-size: 14px;
    }
    .btn:hover { background-color: #0d3a1f; }
</style>
</head>
<body>
<div class="container">
    <a href="dashboard.php" class="back-link">&larr; Back to Dashboard</a>
    <h1>Back Up Your Data</h1>
    <p class="subtitle">Everything is stored in one place on this computer — protect it</p>

    <div class="warning-box">
        <strong>Why this matters:</strong> all your students, payments, and uniform records live in one MySQL database on this one computer. If this computer is lost, damaged, or its files get corrupted, that data is gone unless you've backed it up somewhere else.
    </div>

    <p style="font-weight:600; margin-bottom:10px;">How to back up (takes about 1 minute):</p>
    <ol class="steps">
        <li>Open phpMyAdmin: <a href="http://localhost/phpmyadmin" target="_blank">http://localhost/phpmyadmin</a></li>
        <li>Click on the <code>ummul_bannin_madrasah</code> database in the left sidebar</li>
        <li>Click the <strong>Export</strong> tab at the top</li>
        <li>Leave the default settings ("Quick" export method, "SQL" format) and click <strong>Go</strong></li>
        <li>A file like <code>ummul_bannin_madrasah.sql</code> will download — save it somewhere safe</li>
    </ol>

    <p style="font-weight:600; margin-top:20px; margin-bottom:10px;">Where to keep your backup:</p>
    <ul class="steps">
        <li>A USB flash drive kept separately from this computer</li>
        <li>A cloud storage folder (Google Drive, OneDrive, etc.) so it's safe even if the computer is damaged</li>
        <li>Email it to yourself occasionally as an extra copy</li>
    </ul>

    <p style="font-weight:600; margin-top:20px;">How often?</p>
    <p style="font-size:14px;">A good habit: back up at the <strong>end of every week</strong>, and always right <strong>before</strong> making any big change (like starting a new term or updating the system).</p>

    <a href="http://localhost/phpmyadmin" target="_blank" class="btn">Open phpMyAdmin to Back Up Now</a>
</div>
</body>
</html>
