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
    animation: skyGradient 1000s infinite ease-in-out;
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
    height: 800px;
    left: -59%;
    top: -10%;
    bottom: -10%;

    /* background: url('images/cloud.png') repeat-x; */
    background-size: contain;

    z-index: -5; 
    opacity: 0.12;

    animation: moveClouds 1500s linear infinite;
}

/* @keyframes moveClouds {
    0%   { transform: translateX(0); }
    50%  { transform: translateX(50%); }
    100% { transform: translateX(100%); }
} */

/* ================================
   AIRPLANE ANIMATION
================================ */
.airplane {
    position: fixed;
    width: 100%;
    height: 800px;
    top: -20px;
    left: -20px;
    /* background: url('images/airplane.png') repeat-x; */
    opacity: 0.50;
    z-index: -2;

    animation: flyPlane 455s linear infinite;
    pointer-events: none;

}


</style>

<!-- Background Layers -->
<div class="cloud-layer"></div>
<div class="cloud-layer"></div>
<div class="cloud-layer"></div>

<!-- <img src="images/airplane.png" class="airplane"> -->
