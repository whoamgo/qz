// Grid animation for hero section
class HeroGridAnimation {
    constructor(canvas) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.particles = [];
        this.gridLines = [];
        this.cellSize = 60;

        this.resize();
        this.generateGrid();
        this.animate();

        if ('ResizeObserver' in window) {
            const resizeObserver = new ResizeObserver(entries => {
                for (let entry of entries) {
                    const { width, height } = entry.contentRect;
                    this.canvas.width = width;
                    this.canvas.height = height;
                    this.generateGrid();
                }
            });
            resizeObserver.observe(this.canvas);
        } else {
            window.addEventListener('resize', () => {
                this.resize();
                this.generateGrid();
            });
        }
        setInterval(() => this.spawnParticle(), 180);
    }

    resize() {
        const rect = this.canvas.getBoundingClientRect();
        this.canvas.width = rect.width;
        this.canvas.height = rect.height;
    }

    generateGrid() {
        this.gridLines = [];
        this.edgeSpawnPoints = [];

        const numHorizontal = Math.floor(this.canvas.height / this.cellSize) + 1;
        for (let i = 0; i < numHorizontal; i++) {
            const y = i * this.cellSize;
            this.gridLines.push({
                type: 'horizontal',
                startX: 0,
                startY: y,
                endX: this.canvas.width,
                endY: y,
                index: i
            });
        }

        const numVertical = Math.floor(this.canvas.width / this.cellSize) + 1;
        for (let i = 0; i < numVertical; i++) {
            const x = i * this.cellSize;
            this.gridLines.push({
                type: 'vertical',
                startX: x,
                startY: 0,
                endX: x,
                endY: this.canvas.height,
                index: i
            });
        }

        for (let i = 0; i < numVertical; i++) {
            this.edgeSpawnPoints.push({
                x: i * this.cellSize,
                y: 0,
                direction: 'down',
                lineIndex: i,
                lineType: 'vertical'
            });
        }

        for (let i = 0; i < numVertical; i++) {
            this.edgeSpawnPoints.push({
                x: i * this.cellSize,
                y: this.canvas.height,
                direction: 'up',
                lineIndex: i,
                lineType: 'vertical'
            });
        }

        for (let i = 1; i < numHorizontal - 1; i++) {
            this.edgeSpawnPoints.push({
                x: 0,
                y: i * this.cellSize,
                direction: 'right',
                lineIndex: i,
                lineType: 'horizontal'
            });
        }

        for (let i = 1; i < numHorizontal - 1; i++) {
            this.edgeSpawnPoints.push({
                x: this.canvas.width,
                y: i * this.cellSize,
                direction: 'left',
                lineIndex: i,
                lineType: 'horizontal'
            });
        }
    }

    spawnParticle() {
        if (this.edgeSpawnPoints.length === 0) return;

        const spawnPoint = this.edgeSpawnPoints[Math.floor(Math.random() * this.edgeSpawnPoints.length)];
        const line = this.gridLines.find(l =>
            l.type === spawnPoint.lineType && l.index === spawnPoint.lineIndex
        );

        if (!line) return;

        let startProgress, direction;
        if (spawnPoint.direction === 'down' || spawnPoint.direction === 'right') {
            startProgress = 0;
            direction = 1;
        } else {
            startProgress = 1;
            direction = -1;
        }


        this.particles.push({
            line: line,
            progress: startProgress,
            direction: direction,
            speed: 0.0021 + Math.random() * 0.0035,
            opacity: 0,
            fadeIn: true,
            color: { h: 253, s: 72, l: 54 },
            length: 0.25 + Math.random() * 0.15,
            trail: []
        });
    }

    updateParticles() {
        this.particles = this.particles.filter(particle => {
            particle.progress += particle.speed * particle.direction;

            const line = particle.line;
            const x = line.startX + (line.endX - line.startX) * particle.progress;
            const y = line.startY + (line.endY - line.startY) * particle.progress;

            particle.trail.push({ x, y, progress: particle.progress });

            particle.trail = particle.trail.filter(point => {
                const distance = Math.abs(particle.progress - point.progress);
                return distance <= particle.length;
            });

            if (particle.fadeIn) {
                particle.opacity += 0.03;
                if (particle.opacity >= 1) {
                    particle.opacity = 1;
                    particle.fadeIn = false;
                }
            } else if ((particle.direction === 1 && particle.progress > 0.85) ||
                (particle.direction === -1 && particle.progress < 0.15)) {
                particle.opacity -= 0.03;
            }

            return particle.progress >= -particle.length && particle.progress <= 1 + particle.length && particle.opacity > 0;
        });
    }

    drawGrid() {
        this.ctx.strokeStyle = 'rgba(255, 255, 255, 0.06)';
        this.ctx.lineWidth = 1;

        this.gridLines.forEach(line => {
            this.ctx.beginPath();
            this.ctx.moveTo(line.startX, line.startY);
            this.ctx.lineTo(line.endX, line.endY);
            this.ctx.stroke();
        });
    }

    drawParticles() {
        this.particles.forEach(particle => {
            if (particle.trail.length < 2) return;

            const color = particle.color;

            this.ctx.strokeStyle = `hsl(${color.h}, ${color.s}%, ${color.l}%, ${particle.opacity})`;
            this.ctx.lineWidth = 2;
            this.ctx.lineCap = 'round';

            for (let i = 0; i < particle.trail.length - 1; i++) {
                const point = particle.trail[i];
                const nextPoint = particle.trail[i + 1];

                const fadeProgress = i / (particle.trail.length - 1);
                this.ctx.globalAlpha = particle.opacity * fadeProgress;

                this.ctx.beginPath();
                this.ctx.moveTo(point.x, point.y);
                this.ctx.lineTo(nextPoint.x, nextPoint.y);
                this.ctx.stroke();
            }
        });

        this.ctx.globalAlpha = 1;
    }

    animate() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

        this.drawGrid();
        this.updateParticles();
        this.drawParticles();

        requestAnimationFrame(() => this.animate());
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const heroCanvas = document.getElementById('hero-grid-canvas');
    if (heroCanvas) {
        new HeroGridAnimation(heroCanvas);
    }
});
