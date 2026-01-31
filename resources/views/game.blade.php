<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Super Penalti: Pilih Level & Suara</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap');

        body {
            font-family: 'Press Start 2P', cursive, sans-serif; /* Font gaya retro */
            background-color: #20232a;
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
            overflow: hidden;
            touch-action: none;
        }

        /* --- TAMPILAN MENU --- */
        #menuScreen {
            text-align: center;
            background: rgba(0, 0, 0, 0.8);
            padding: 40px;
            border-radius: 20px;
            border: 4px solid #f1c40f;
            box-shadow: 0 0 20px #f1c40f;
        }

        h1 { margin-bottom: 30px; color: #f1c40f; text-shadow: 4px 4px #c0392b; font-size: 24px; line-height: 1.5; }
        
        .btn-level {
            display: block;
            width: 100%;
            padding: 15px;
            margin: 10px 0;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            font-family: inherit;
            font-size: 14px;
            cursor: pointer;
            transition: 0.2s;
            box-shadow: 0 5px #2980b9;
        }

        .btn-level:active { transform: translateY(5px); box-shadow: none; }
        .btn-level:hover { background: #5dade2; }
        
        .lvl-1 { background: #2ecc71; box-shadow: 0 5px #27ae60; }
        .lvl-2 { background: #e67e22; box-shadow: 0 5px #d35400; }
        .lvl-3 { background: #e74c3c; box-shadow: 0 5px #c0392b; }

        /* --- TAMPILAN GAME --- */
        #gameScreen { display: none; position: relative; }
        
        #gameCanvas {
            border: 4px solid #fff;
            border-radius: 10px;
            background: #27ae60;
            box-shadow: 0 10px 20px rgba(0,0,0,0.5);
            cursor: crosshair;
        }

        .hud {
            display: flex;
            justify-content: space-between;
            width: 100%;
            margin-bottom: 10px;
            font-size: 12px;
        }

        #btnBack {
            margin-top: 10px;
            background: #95a5a6;
            padding: 10px 20px;
            border: none;
            color: white;
            font-family: inherit;
            font-size: 10px;
            cursor: pointer;
            border-radius: 5px;
        }
    </style>
</head>
<body>

    <div id="menuScreen">
        <h1>⚽ SUPER<br>PENALTI</h1>
        <p style="font-size: 10px; margin-bottom: 20px; color: #ccc;">Pilih Tingkat Kesulitan:</p>
        <button class="btn-level lvl-1" onclick="startGame(1)">Level 1: Pemula</button>
        <button class="btn-level lvl-2" onclick="startGame(2)">Level 2: Amatir</button>
        <button class="btn-level lvl-3" onclick="startGame(3)">Level 3: Pro</button>
        <p style="font-size: 8px; margin-top: 20px;">*Klik untuk Aktifkan Suara</p>
    </div>

    <div id="gameScreen">
        <div class="hud">
            <span id="scoreText">SKOR: 0</span>
            <span id="levelDisplay" style="color: #f1c40f;">LEVEL 1</span>
        </div>
        <canvas id="gameCanvas" width="360" height="500"></canvas>
        <div style="text-align: center;">
            <button id="btnBack" onclick="backToMenu()">⬅ KEMBALI KE MENU</button>
        </div>
    </div>

<script>
    // --- AUDIO ENGINE (SYNTHESIZER) ---
    // Membuat suara beep/boop tanpa file mp3 eksternal
    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    
    function playSound(type) {
        if (audioCtx.state === 'suspended') audioCtx.resume();
        const osc = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();
        osc.connect(gainNode);
        gainNode.connect(audioCtx.destination);

        const now = audioCtx.currentTime;

        if (type === 'kick') {
            // Suara tendangan (Low thud)
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(150, now);
            osc.frequency.exponentialRampToValueAtTime(0.01, now + 0.5);
            gainNode.gain.setValueAtTime(1, now);
            gainNode.gain.exponentialRampToValueAtTime(0.01, now + 0.5);
            osc.start(now);
            osc.stop(now + 0.5);
        } else if (type === 'goal') {
            // Suara Gol (Melody naik)
            osc.type = 'square';
            osc.frequency.setValueAtTime(400, now);
            osc.frequency.setValueAtTime(600, now + 0.1);
            osc.frequency.setValueAtTime(800, now + 0.2);
            gainNode.gain.setValueAtTime(0.3, now);
            gainNode.gain.exponentialRampToValueAtTime(0.01, now + 1);
            osc.start(now);
            osc.stop(now + 1);
        } else if (type === 'miss') {
            // Suara Gagal (Buzzer turun)
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(200, now);
            osc.frequency.linearRampToValueAtTime(50, now + 0.5);
            gainNode.gain.setValueAtTime(0.5, now);
            gainNode.gain.linearRampToValueAtTime(0.01, now + 0.5);
            osc.start(now);
            osc.stop(now + 0.5);
        } else if (type === 'whistle') {
            // Peluit (High frequency)
            osc.type = 'sine';
            osc.frequency.setValueAtTime(1500, now);
            osc.frequency.setValueAtTime(2000, now + 0.1);
            gainNode.gain.setValueAtTime(0.2, now);
            osc.start(now);
            osc.stop(now + 0.3);
        }
    }

    // --- VARIABEL GAME ---
    const canvas = document.getElementById('gameCanvas');
    const ctx = canvas.getContext('2d');
    let gameLoopId;
    let score = 0;
    let currentLevel = 1;
    let isGameOver = false;

    // Objek
    const ball = { x: 180, y: 450, r: 12, dx: 0, dy: 0, moving: false, color: 'white' };
    const goal = { x: 30, y: 20, w: 300, h: 80 }; // Gawang
    const keeper = { x: 160, y: 70, w: 40, h: 60, speed: 0, dx: 1, color: '#e74c3c' };

    // --- LOGIC MENU ---
    function startGame(level) {
        // Setup Level
        currentLevel = level;
        score = 0;
        document.getElementById('scoreText').innerText = "SKOR: 0";
        document.getElementById('levelDisplay').innerText = "LEVEL " + level;

        // Set Kesulitan Kiper
        if (level === 1) {
            keeper.speed = 1; // Sangat pelan
            keeper.w = 70;
        } else if (level === 2) {
            keeper.speed = 4; // Sedang
            keeper.w = 90; // Kiper sedikit lebih besar
        } else if (level === 3) {
            keeper.speed = 8; // Cepat (Pro)
            keeper.w = 120; // Kiper besar
        }

        // UI Switch
        document.getElementById('menuScreen').style.display = 'none';
        document.getElementById('gameScreen').style.display = 'block';

        playSound('whistle');
        resetBall();
        isGameOver = false;
        
        // Start Loop
        if (gameLoopId) cancelAnimationFrame(gameLoopId);
        update();
    }

    function backToMenu() {
        document.getElementById('gameScreen').style.display = 'none';
        document.getElementById('menuScreen').style.display = 'block';
        cancelAnimationFrame(gameLoopId);
    }

    // --- LOGIC GAMEPLAY ---
    function resetBall() {
        ball.moving = false;
        ball.x = canvas.width / 2;
        ball.y = 450;
        ball.dx = 0;
        ball.dy = 0;
    }

    function update() {
        // 1. Bersihkan Canvas
        ctx.fillStyle = '#27ae60'; // Rumput
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        // Gambar Kotak Penalti
        ctx.strokeStyle = 'white';
        ctx.lineWidth = 3;
        ctx.strokeRect(60, 0, 240, 150);
        ctx.beginPath();
        ctx.arc(canvas.width/2, 150, 40, 0, Math.PI, false);
        ctx.stroke();

        // 2. Gambar Gawang (Jaring)
        ctx.fillStyle = '#333';
        ctx.fillRect(goal.x, goal.y, goal.w, goal.h); // Dalam gawang gelap
        ctx.strokeStyle = 'white';
        ctx.lineWidth = 5;
        ctx.strokeRect(goal.x, goal.y, goal.w, goal.h); // Tiang gawang

        // 3. Logic & Gambar Kiper
        if (!isGameOver) {
            keeper.x += keeper.speed * keeper.dx;
            
            // Kiper pantul tembok gawang
            // Level 3 kiper kadang berubah arah acak (kecerdasan buatan sederhana)
            if (currentLevel === 3 && Math.random() < 0.02) keeper.dx *= -1;

            if (keeper.x <= goal.x || keeper.x + keeper.w >= goal.x + goal.w) {
                keeper.dx *= -1;
            }
        }

        ctx.fillStyle = keeper.color;
        ctx.fillRect(keeper.x, keeper.y, keeper.w, keeper.h);
        // Baju Kiper
        ctx.fillStyle = '#fff'; 
        ctx.fillText(currentLevel === 3 ? "PRO" : "GK", keeper.x + 5, keeper.y + 30);

        // 4. Logic & Gambar Bola
        if (ball.moving) {
            ball.x += ball.dx;
            ball.y += ball.dy;

            // Cek Tabrakan Kiper (Gagal)
            if (
                ball.x > keeper.x && 
                ball.x < keeper.x + keeper.w &&
                ball.y > keeper.y &&
                ball.y < keeper.y + keeper.h
            ) {
                playSound('miss');
                alert("Ditepis Kiper! 🧤");
                resetBall();
            }

            // Cek Gol
            else if (ball.y < goal.y + goal.h) {
                if (ball.x > goal.x && ball.x < goal.x + goal.w) {
                    playSound('goal');
                    score++;
                    document.getElementById('scoreText').innerText = "SKOR: " + score;
                    // Jeda sebentar sebelum reset
                    ball.moving = false; // Stop bola agar tidak double alert
                    setTimeout(() => {
                        resetBall();
                    }, 500);
                } else {
                    playSound('miss');
                    alert("Tendangan Melebar! ❌");
                    resetBall();
                }
            }
        }

        // Gambar Bola
        ctx.fillStyle = ball.color;
        ctx.beginPath();
        ctx.arc(ball.x, ball.y, ball.r, 0, Math.PI * 2);
        ctx.fill();
        ctx.strokeStyle = '#333';
        ctx.lineWidth = 2;
        ctx.stroke();

        gameLoopId = requestAnimationFrame(update);
    }

    // --- KONTROL (TOUCH & CLICK) ---
    canvas.addEventListener('mousedown', shoot);
    canvas.addEventListener('touchstart', (e) => {
        e.preventDefault();
        shoot(e.touches[0]);
    });

    function shoot(e) {
        if (ball.moving) return;
        
        // Ambil posisi klik untuk menentukan arah tendangan
        const rect = canvas.getBoundingClientRect();
        const clientX = e.clientX || e.pageX;
        const clientY = e.clientY || e.pageY;
        
        const targetX = clientX - rect.left;
        const targetY = clientY - rect.top;

        // Hitung sudut tembakan
        const angle = Math.atan2(targetY - ball.y, targetX - ball.x);
        const velocity = 15; // Kecepatan bola

        ball.dx = Math.cos(angle) * velocity;
        ball.dy = Math.sin(angle) * velocity;
        ball.moving = true;

        playSound('kick');
    }

</script>
</body>
</html>