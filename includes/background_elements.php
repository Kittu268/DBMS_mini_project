<!-- =======================================
     GLOBAL BACKGROUND — FINAL VERSION
     No errors, No PHP output
======================================= -->

<style>
/* ================================
   SKY BACKGROUND ANIMATION
================================ */
@keyframes skyGradient {
    0%   { background: linear-gradient(to bottom, #a1c4fd, #c2e9fb); }
    50%  { background: linear-gradient(to bottom, #89f7fe, #66a6ff); }
    100% { background: linear-gradient(to bottom, #a1c4fd, #c2e9fb); }
}

body {
    animation: skyGradient 100s infinite ease-in-out;
    overflow-x: hidden;
    margin: 0;
    padding: 0;
    position: relative;
    z-index: 10; /* UI stays above background */
}

/* ================================
   CLOUD LAYERS
================================ */
.cloud-layer {
    position: fixed;
    width: 300%;
    height: 600px;
    left: -100%;
    top: 0;

    background: url('images/cloud.png') repeat-x;
    background-size: contain;

    z-index: -10; 
    opacity: 0.18;

    animation: moveClouds 150s linear infinite;
}

.cloud-layer:nth-child(2) {
    top: 40%;
    opacity: 0.20;
    animation-duration: 190s;
}

.cloud-layer:nth-child(3) {
    top: 70%;
    opacity: 0.15;
    animation-duration: 220s;
}

@keyframes moveClouds {
    0%   { transform: translateX(0); }
    100% { transform: translateX(100%); }
}

/* ================================
   AIRPLANE ANIMATION
================================ */
.airplane {
    position: fixed;
    width: 350px;
    top: 20%;
    left: -200px;

    opacity: 0.12;
    z-index: -20;

    animation: flyPlane 45s linear infinite;
}

@keyframes flyPlane {
    0%   { transform: translateX(0) rotate(2deg); }
    50%  { transform: translateX(120vw) rotate(-3deg); }
    100% { transform: translateX(0) rotate(2deg); }
}
</style>

<!-- Background Layers -->
<div class="cloud-layer"></div>
<div class="cloud-layer"></div>
<div class="cloud-layer"></div>

<img src="images/airplane.png" class="airplane">
