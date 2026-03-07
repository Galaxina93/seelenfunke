<script>
    // Globale Funktion, die völlig unabhängig von Lade-Reihenfolgen funktioniert
    window.startUniverseEngine = function(container, userCount) {
        if (!container) return;

        // Sucht sich automatisch das Canvas in der jeweiligen Section
        const canvas = container.querySelector('canvas');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');

        // Fallback-Größe, falls der Container im allerersten Moment noch 0 ist
        let width = container.offsetWidth > 0 ? container.offsetWidth : window.innerWidth;
        let height = container.offsetHeight > 0 ? container.offsetHeight : window.innerHeight;

        canvas.width = width;
        canvas.height = height;

        let stars = [];
        let planets = [];
        let meteors = [];
        let dust = [];

        const config = {
            starsCount: userCount,
            planetsCount: userCount > 0 ? 2 : 0,
            dustCount: userCount > 0 ? 30 : 0,
            meteorsCount: userCount > 0 ? 8 : 0,
            colors: {
                gold: 'rgba(197, 160, 89,',
                white: 'rgba(255, 255, 255,',
                blue: 'rgba(100, 150, 255,',
                copper: 'rgba(217, 119, 83,'
            }
        };

        const random = (min, max) => Math.random() * (max - min) + min;

        // 1. STERNE
        for (let i = 0; i < config.starsCount; i++) {
            stars.push({
                x: random(0, width),
                y: random(0, height),
                r: random(0.3, 1.5),
                baseAlpha: random(0.2, 0.8),
                angle: random(0, Math.PI * 2),
                speed: random(0.01, 0.04),
                vx: random(-0.1, 0.1),
                vy: random(-0.1, 0.1),
                color: Math.random() > 0.8 ? config.colors.gold : config.colors.white
            });
        }

        // 2. MONDE / SONNEN
        for (let i = 0; i < config.planetsCount; i++) {
            planets.push({
                x: random(0, width),
                y: random(0, height),
                r: random(100, 300),
                vx: random(-0.03, 0.03),
                vy: random(-0.03, 0.03),
                color: [config.colors.gold, config.colors.blue, config.colors.copper][Math.floor(Math.random() * 3)],
                maxAlpha: random(0.02, 0.06)
            });
        }

        // 3. STERNENSTAUB
        for (let i = 0; i < config.dustCount; i++) {
            dust.push({
                x: random(0, width),
                y: random(0, height),
                r: random(1, 2.5),
                vx: random(-0.2, 0.2),
                vy: random(-0.1, 0.1),
                angle: random(0, Math.PI * 2),
                floatSpeed: random(0.01, 0.03),
                floatRange: random(15, 40),
                baseY: random(0, height),
                alpha: random(0.1, 0.2)
            });
        }

        // 4. METEORITEN
        const spawnMeteor = (isInitial = false) => {
            const edge = Math.floor(random(0, 4));
            let x, y, vx, vy;
            const speed = random(0.6, 2.0);
            const angle = random(0, Math.PI * 2);

            if (isInitial) {
                x = random(0, width);
                y = random(0, height);
            } else {
                if (edge === 0) { x = -50; y = random(0, height); }
                else if (edge === 1) { x = width + 50; y = random(0, height); }
                else if (edge === 2) { x = random(0, width); y = -50; }
                else { x = random(0, width); y = height + 50; }
            }

            vx = Math.cos(angle) * speed;
            vy = Math.sin(angle) * speed;

            meteors.push({
                x: x, y: y, vx: vx, vy: vy,
                size: random(1.0, 2.5),
                alpha: random(0.3, 0.8),
                length: random(50, 150),
                color: Math.random() > 0.5 ? config.colors.gold : config.colors.white
            });
        };

        for (let i = 0; i < config.meteorsCount; i++) {
            spawnMeteor(true);
        }

        // RENDER LOOP
        const loop = () => {
            ctx.clearRect(0, 0, width, height);

            planets.forEach(p => {
                p.x += p.vx;
                p.y += p.vy;
                if (p.x - p.r > width) p.x = -p.r;
                if (p.x + p.r < 0) p.x = width + p.r;
                if (p.y - p.r > height) p.y = -p.r;
                if (p.y + p.r < 0) p.y = height + p.r;

                let grad = ctx.createRadialGradient(p.x - p.r * 0.2, p.y - p.r * 0.2, 0, p.x, p.y, p.r);
                grad.addColorStop(0, `${p.color}${p.maxAlpha})`);
                grad.addColorStop(1, 'rgba(0,0,0,0)');

                ctx.beginPath();
                ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                ctx.fillStyle = grad;
                ctx.fill();
            });

            stars.forEach(s => {
                s.angle += s.speed;
                s.x += s.vx;
                s.y += s.vy;

                if (s.x > width) s.x = 0;
                if (s.x < 0) s.x = width;
                if (s.y > height) s.y = 0;
                if (s.y < 0) s.y = height;

                let currentAlpha = s.baseAlpha + Math.sin(s.angle) * 0.4;
                if (currentAlpha < 0) currentAlpha = 0;

                ctx.beginPath();
                ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
                ctx.fillStyle = `${s.color}${currentAlpha})`;
                ctx.fill();
            });

            dust.forEach(d => {
                d.x += d.vx;
                d.baseY += d.vy;
                d.angle += d.floatSpeed;
                d.y = d.baseY + Math.sin(d.angle) * d.floatRange;

                if (d.x > width + 10) d.x = -10;
                if (d.x < -10) d.x = width + 10;
                if (d.baseY > height + 40) d.baseY = -40;
                if (d.baseY < -40) d.baseY = height + 40;

                ctx.beginPath();
                ctx.arc(d.x, d.y, d.r, 0, Math.PI * 2);
                ctx.fillStyle = `${config.colors.gold}${d.alpha})`;
                ctx.shadowBlur = d.r * 2;
                ctx.shadowColor = `${config.colors.gold}${d.alpha})`;
                ctx.fill();
                ctx.shadowBlur = 0;
            });

            for (let i = meteors.length - 1; i >= 0; i--) {
                let m = meteors[i];
                m.x += m.vx;
                m.y += m.vy;

                if (m.x > width + 150 || m.x < -150 || m.y > height + 150 || m.y < -150) {
                    meteors.splice(i, 1);
                    spawnMeteor();
                    continue;
                }

                ctx.beginPath();
                ctx.moveTo(m.x, m.y);
                ctx.lineTo(m.x - (m.vx * m.length), m.y - (m.vy * m.length));
                let grad = ctx.createLinearGradient(m.x, m.y, m.x - (m.vx * m.length), m.y - (m.vy * m.length));
                grad.addColorStop(0, `${m.color}${m.alpha})`);
                grad.addColorStop(1, 'rgba(0,0,0,0)');

                ctx.strokeStyle = grad;
                ctx.lineWidth = m.size * 0.6;
                ctx.lineCap = 'round';
                ctx.stroke();

                ctx.beginPath();
                ctx.arc(m.x, m.y, m.size, 0, Math.PI * 2);
                ctx.fillStyle = `${m.color}${m.alpha + 0.3})`;
                ctx.shadowBlur = m.size * 3;
                ctx.shadowColor = `${m.color}${m.alpha})`;
                ctx.fill();
                ctx.shadowBlur = 0;
            }

            requestAnimationFrame(loop);
        };
        loop();

        // KUGELSICHER: Passt das Canvas exakt an, sobald die Section fertig gezeichnet ist
        const resizeObserver = new ResizeObserver(entries => {
            for (let entry of entries) {
                if (entry.contentRect.width > 0) {
                    width = canvas.width = entry.contentRect.width;
                    height = canvas.height = entry.contentRect.height;
                }
            }
        });
        resizeObserver.observe(container);
    };
</script>
