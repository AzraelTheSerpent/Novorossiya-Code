{{-- PWA: установка, офлайн-режим и обновления. Подключается в <head> публичных страниц. --}}
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#547864">
<meta name="application-name" content="Код Новороссии">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Код региона">
<link rel="icon" href="/icon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="stylesheet" href="/pwa.css">
@if(isset($installUi) && !$installUi)
    <script>window.KOD_PWA_INSTALL_UI = false;</script>
@endif
<script src="/pwa.js" defer></script>
