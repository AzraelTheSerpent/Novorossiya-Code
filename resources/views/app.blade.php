<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>Код Новороссии · Кабинет</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Unbounded:wght@500;700;800&family=Manrope:wght@400;600;700;800&display=swap"
        rel="stylesheet">
    @vite(['resources/css/admin.css', 'resources/js/app.js'])
    @include('partials.pwa', ['installUi' => !request()->routeIs('admin.*')])
    @inertiaHead
</head>
<body>
@inertia
</body>
</html>
