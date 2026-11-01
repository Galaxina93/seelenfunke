<script>
window.funkiHub = function(initialModelPath, initialImagePath, initialLevel = 1) {
    if (window.Alpine) {
        try {
            window.Alpine.store('funki', {
                activeLevel: Math.min(10, initialLevel || 1),
                setLevel(level) { this.activeLevel = level; }
            });
        } catch(e) {}
    }

    return {
        currentImagePath: initialImagePath || '',
        activeModelLevel: Math.min(10, initialLevel || 1),
        initFunki() {
            if (window.Alpine && window.Alpine.store('funki')) {
                window.Alpine.store('funki').setLevel(Math.min(10, this.activeModelLevel || 1));
            }
        }
    };
};
</script>
