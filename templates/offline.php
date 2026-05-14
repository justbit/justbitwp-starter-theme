<?php
/**
 * Offline page — mostrata dal Service Worker quando la nav fallisce.
 *
 * Minimal: no asset dependencies, inline CSS, niente fetch. Funziona anche
 * con cache vuota.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?php echo esc_html__( 'Sei offline', 'justbitwp-starter' ); ?> — <?php bloginfo( 'name' ); ?></title>
    <style>
    :root { --bg: #fdfbf6; --fg: #1f1813; --accent: #a04030; }
    @media (prefers-color-scheme: dark) {
        :root { --bg: #1f1813; --fg: #fdfbf6; --accent: #c89968; }
    }
    *,*::before,*::after { box-sizing: border-box; }
    html,body { height: 100%; margin: 0; }
    body {
        background: var(--bg); color: var(--fg);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
        display: grid; place-items: center; padding: 2rem;
        line-height: 1.5;
    }
    .wrap { max-width: 480px; text-align: center; }
    h1 { font-size: 2rem; margin: 0 0 0.5rem; letter-spacing: -0.02em; }
    p { margin: 0.5rem 0; opacity: 0.8; }
    button {
        margin-top: 1.5rem; padding: 0.75rem 1.5rem; font-size: 1rem;
        background: var(--accent); color: #fff; border: 0; border-radius: 8px;
        cursor: pointer; font-weight: 600;
    }
    button:hover { transform: translateY(-1px); transition: transform 200ms; }
    .dot {
        display: inline-block; width: 10px; height: 10px; background: var(--accent);
        border-radius: 50%; margin-right: 0.5rem; animation: pulse 1.6s infinite;
    }
    @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.4; } }
    </style>
</head>
<body>
    <main class="wrap">
        <p><span class="dot" aria-hidden="true"></span><?php echo esc_html__( 'Connessione assente', 'justbitwp-starter' ); ?></p>
        <h1><?php echo esc_html__( 'Sei offline', 'justbitwp-starter' ); ?></h1>
        <p><?php echo esc_html__( 'Sembra che la connessione internet non sia disponibile. Le pagine già visitate restano leggibili dalla cache.', 'justbitwp-starter' ); ?></p>
        <button onclick="location.reload()"><?php echo esc_html__( 'Riprova', 'justbitwp-starter' ); ?></button>
    </main>
</body>
</html>
