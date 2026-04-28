<style>
    #threejs-match3-container canvas {
        position: absolute; top: 0; left: 0; right: 0; bottom: 0; z-index: 10;
        display: block; width: 100% !important; height: 100% !important; outline: none;
        touch-action: none; /* WICHTIG FÜR MOBILE DRAG AND DROP */
        -webkit-user-select: none; user-select: none;
    }
    .floating-score {
        position: absolute; font-family: ui-sans-serif, system-ui, sans-serif; font-weight: 900; font-size: 1.5rem;
        pointer-events: none; z-index: 100; transform: translate(-50%, -50%);
        animation: floatUp 1.2s cubic-bezier(0.16, 1, 0.3, 1) forwards; text-shadow: 0px 4px 10px rgba(0,0,0,0.9), 0px 0px 8px currentColor;
    }
    .floating-bonus {
        font-size: 2rem; color: #f472b6; animation: floatUpBonus 1.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    @media (min-width: 640px) {
        .floating-score { font-size: 2.5rem; }
        .floating-bonus { font-size: 3.5rem; }
    }
    @keyframes floatUp { 0% { transform: translate(-50%, -50%) scale(0.5); opacity: 1; } 20% { transform: translate(-50%, -100%) scale(1.5); opacity: 1; } 100% { transform: translate(-50%, -200%) scale(1); opacity: 0; } }
    @keyframes floatUpBonus { 0% { transform: translate(-50%, -50%) scale(0.5) rotate(-10deg); opacity: 1; } 30% { transform: translate(-50%, -150%) scale(1.5) rotate(5deg); opacity: 1; } 100% { transform: translate(-50%, -300%) scale(1.2) rotate(0deg); opacity: 0; } }

    input[type=range].volume-slider { -webkit-appearance: none; width: 100%; background: transparent; }
    input[type=range].volume-slider::-webkit-slider-thumb { -webkit-appearance: none; height: 16px; width: 16px; border-radius: 50%; background: #10b981; cursor: pointer; margin-top: -6px; box-shadow: 0 0 10px rgba(16, 185, 129, 0.5); }
    input[type=range].volume-slider::-webkit-slider-runnable-track { width: 100%; height: 4px; cursor: pointer; background: #374151; border-radius: 2px; }
</style>
