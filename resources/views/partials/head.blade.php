<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="theme-color" content="#FF7A31" />

<title>{{ ! empty($title) ? $title.' - ' : '' }}Aceros el Águila</title>

<link rel="icon" type="image/png" href="{{ asset('img/logo.png') }}" />

@vite(['resources/css/app.css', 'resources/js/app.js'])