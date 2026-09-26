<script>
    (() => {
        const root = document.documentElement;
        const mode = root.dataset.themeMode;
        const hour = new Date().getHours();
        const theme = mode === 'light' || mode === 'dark'
            ? mode
            : (hour >= 6 && hour < 18 ? 'light' : 'dark');
        root.dataset.theme = theme;
        root.dataset.bsTheme = theme;
        root.style.colorScheme = theme;
    })();
</script>
