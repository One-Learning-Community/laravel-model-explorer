<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Model Explorer</title>
    <script>
        (function () {
            var stored = localStorage.getItem('model-explorer-theme');
            var dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.setAttribute('data-theme', stored || (dark ? 'night' : 'light'));
        })();
    </script>
    <link rel="stylesheet" href="{{ url(config('model-explorer.path') . '/assets/app.css') }}">
</head>
<body>
    <script>window.modelExplorerBasePath = '/{{ config('model-explorer.path') }}'</script>
    <div id="app"></div>
    <script type="module" src="{{ url(config('model-explorer.path') . '/assets/app.js') }}"></script>
</body>
</html>
