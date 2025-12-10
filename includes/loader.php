<?php
/**
 * Page Loader Component
 *
 * Traffic-themed loading animation shown during page load
 * Automatically hides when page is fully loaded
 *
 * @package TrafficCitationSystem
 */
?>
<!-- Page Loader Overlay -->
<div id="pageLoader" class="page-loader-overlay">
    <div class="loader-container">
        <!-- Logo and Title Section -->
        <div class="logo-section">
            <img src="../assets/img/LOGO1.png" alt="Logo" class="loader-logo">
            <h1 class="loader-title">B-TRACS</h1>
        </div>

        <!-- Morph Animation Scene -->
        <div class="scene">
            <!-- The Main Changing Shape -->
            <div class="morph-object state-light" id="morphShape"></div>
            <!-- The Cone Base (Only visible in cone state) -->
            <div class="cone-base" id="coneBase"></div>
        </div>

        <!-- The Glass Badge -->
        <div class="info-card">
            <div class="status-dot" id="statusDot"></div>
            <div class="status-text" id="statusText">LOADING...</div>
        </div>
    </div>
</div>

<style>
/* --- PAGE LOADER OVERLAY --- */
.page-loader-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: #eef2f6;
    /* Subtle Dot Map Pattern */
    background-image: radial-gradient(#dbe3ed 2px, transparent 2px);
    background-size: 24px 24px;
    z-index: 9999;
    display: flex;
    justify-content: center;
    align-items: center;
    transition: opacity 0.5s ease, visibility 0.5s ease;
}

.page-loader-overlay.loaded {
    opacity: 0;
    visibility: hidden;
}

/* --- LOADER STYLES --- */
.loader-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 25px;
    padding-top: 5vh;
}

/* --- LOGO AND TITLE SECTION --- */
.logo-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    animation: logo-fade-in 0.8s ease-out;
}

.loader-logo {
    width: 90px;
    height: 90px;
    object-fit: contain;
    filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.15));
    animation: logo-pulse 2s ease-in-out infinite;
}

.loader-title {
    font-size: 1.875rem;
    font-weight: 800;
    color: #185593;
    letter-spacing: 3px;
    text-transform: uppercase;
    margin: 0;
    text-shadow:
        0 2px 4px rgba(24, 85, 147, 0.2),
        0 4px 8px rgba(24, 85, 147, 0.1);
    animation: title-shimmer 3s ease-in-out infinite;
}

.scene {
    position: relative;
    width: 120px;
    height: 120px;
    display: flex;
    justify-content: center;
    align-items: center;
    perspective: 1000px;
}

/* --- THE MORPH OBJECT --- */
.morph-object {
    width: 60px;
    height: 70px;
    position: relative;
    z-index: 10;
    transition:
        clip-path 0.6s cubic-bezier(0.68, -0.55, 0.27, 1.55),
        background-color 0.5s ease,
        transform 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: 0 10px 20px rgba(0,0,0,0.15);
}

/* --- STATE 1: STOP LIGHT --- */
.morph-object.state-light {
    background: linear-gradient(145deg, #2d2d2d, #1a1a1a);
    clip-path: polygon(25% 0%, 25% 5%, 15% 8%, 15% 92%, 25% 95%, 25% 100%, 75% 100%, 75% 95%, 85% 92%, 85% 8%, 75% 5%, 75% 0%);
    transform: rotate(0deg) scale(1.1);
    box-shadow:
        0 15px 35px rgba(0,0,0,0.4),
        inset 0 2px 4px rgba(255,255,255,0.1),
        inset 0 -2px 4px rgba(0,0,0,0.3);
    border: 1px solid rgba(255,255,255,0.05);
    animation: pulse-glow 3s ease-in-out infinite;
}

/* Traffic Light Housing Details */
.morph-object.state-light::before {
    content: '';
    position: absolute;
    inset: 0;
    opacity: 1;
    background:
        /* Red Light */
        radial-gradient(circle at 50% 18%, rgba(255, 82, 82, 0.3) 0%, transparent 50%),
        radial-gradient(circle at 50% 18%, #ff5252 8px, transparent 9px),
        radial-gradient(circle at 50% 18%, rgba(0,0,0,0.4) 9px, transparent 10px),
        /* Yellow Light */
        radial-gradient(circle at 50% 50%, rgba(255, 235, 59, 0.3) 0%, transparent 50%),
        radial-gradient(circle at 50% 50%, #ffeb3b 8px, transparent 9px),
        radial-gradient(circle at 50% 50%, rgba(0,0,0,0.4) 9px, transparent 10px),
        /* Green Light */
        radial-gradient(circle at 50% 82%, rgba(76, 175, 80, 0.3) 0%, transparent 50%),
        radial-gradient(circle at 50% 82%, #4caf50 8px, transparent 9px),
        radial-gradient(circle at 50% 82%, rgba(0,0,0,0.4) 9px, transparent 10px);
    animation: traffic-sequence 3s infinite;
}

/* Light Reflections */
.morph-object.state-light::after {
    content: '';
    position: absolute;
    inset: 0;
    opacity: 1;
    background:
        /* Red reflection */
        radial-gradient(ellipse 4px 3px at 48% 16%, rgba(255,255,255,0.6), transparent),
        /* Yellow reflection */
        radial-gradient(ellipse 4px 3px at 48% 48%, rgba(255,255,255,0.6), transparent),
        /* Green reflection */
        radial-gradient(ellipse 4px 3px at 48% 80%, rgba(255,255,255,0.6), transparent);
    pointer-events: none;
}

/* --- STATE 2: TRAFFIC CONE --- */
.morph-object.state-cone {
    background: linear-gradient(135deg, #FF6700 0%, #FF8533 50%, #FF6700 100%);
    clip-path: polygon(25% 15%, 15% 100%, 85% 100%, 75% 15%);
    transform: rotate(0deg) translateY(0);
    box-shadow:
        0 12px 30px rgba(255, 103, 0, 0.4),
        inset 0 2px 4px rgba(255, 255, 255, 0.3),
        inset 0 -2px 4px rgba(0, 0, 0, 0.2);
    animation: cone-sway 2s ease-in-out infinite;
}

/* Cone Stripes */
.morph-object.state-cone::before {
    content: '';
    position: absolute;
    inset: 0;
    opacity: 1;
    transition: opacity 0.3s;
    background: linear-gradient(
        to bottom,
        transparent 35%,
        rgba(255,255,255,0.9) 35%,
        rgba(255,255,255,0.9) 45%,
        transparent 45%,
        transparent 55%,
        rgba(255,255,255,0.9) 55%,
        rgba(255,255,255,0.9) 65%,
        transparent 65%
    );
}

.morph-object.state-cone::after {
    opacity: 0;
}

/* --- CONE BASE --- */
.cone-base {
    position: absolute;
    bottom: 25px;
    width: 70px;
    height: 10px;
    background: linear-gradient(180deg, #CC5200 0%, #FF6700 50%, #CC5200 100%);
    border-radius: 5px;
    z-index: 5;
    transform: scaleX(0);
    opacity: 0;
    transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow:
        0 6px 15px rgba(0, 0, 0, 0.3),
        inset 0 1px 2px rgba(255, 255, 255, 0.3),
        inset 0 -1px 2px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(0, 0, 0, 0.2);
}

.cone-base.visible {
    transform: scaleX(1);
    opacity: 1;
}

/* --- BADGE --- */
.info-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.7));
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
    padding: 14px 28px;
    border-radius: 50px;
    border: 1px solid rgba(255, 255, 255, 1);
    box-shadow:
        0 8px 25px rgba(0, 0, 0, 0.08),
        inset 0 1px 2px rgba(255, 255, 255, 0.5),
        inset 0 -1px 2px rgba(0, 0, 0, 0.05);
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 200px;
    justify-content: center;
    animation: badge-float 3s ease-in-out infinite;
}

.status-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background-color: #4285F4;
    transition: all 0.4s ease;
    box-shadow:
        0 0 0 3px rgba(255,255,255,0.6),
        0 0 15px currentColor,
        inset 0 1px 1px rgba(255,255,255,0.5);
    animation: dot-pulse 1.5s ease-in-out infinite;
}

.status-text {
    font-size: 0.875rem;
    font-weight: 800;
    color: #2d2d2d;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8);
}

/* --- ANIMATIONS --- */
@keyframes traffic-sequence {
    0%, 100% {
        filter: brightness(1) drop-shadow(0 0 8px rgba(255, 82, 82, 0.8));
    }
    25% {
        filter: brightness(1.3) drop-shadow(0 0 12px rgba(255, 82, 82, 1));
    }
    33% {
        filter: brightness(1) drop-shadow(0 0 8px rgba(255, 235, 59, 0.8));
    }
    50% {
        filter: brightness(1.3) drop-shadow(0 0 12px rgba(255, 235, 59, 1));
    }
    66% {
        filter: brightness(1) drop-shadow(0 0 8px rgba(76, 175, 80, 0.8));
    }
    83% {
        filter: brightness(1.3) drop-shadow(0 0 12px rgba(76, 175, 80, 1));
    }
}

@keyframes pulse-glow {
    0%, 100% {
        box-shadow:
            0 15px 35px rgba(0,0,0,0.4),
            inset 0 2px 4px rgba(255,255,255,0.1),
            inset 0 -2px 4px rgba(0,0,0,0.3);
    }
    50% {
        box-shadow:
            0 15px 35px rgba(0,0,0,0.4),
            inset 0 2px 4px rgba(255,255,255,0.15),
            inset 0 -2px 4px rgba(0,0,0,0.2),
            0 0 20px rgba(255,235,59,0.3);
    }
}

@keyframes cone-sway {
    0%, 100% {
        transform: rotate(0deg) translateY(0) translateX(0);
    }
    25% {
        transform: rotate(-2deg) translateY(-2px) translateX(-1px);
    }
    75% {
        transform: rotate(2deg) translateY(-2px) translateX(1px);
    }
}

@keyframes badge-float {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-5px);
    }
}

@keyframes dot-pulse {
    0%, 100% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.2);
        opacity: 0.8;
    }
}

@keyframes logo-fade-in {
    0% {
        opacity: 0;
        transform: translateY(-20px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes logo-pulse {
    0%, 100% {
        transform: scale(1);
        filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.15));
    }
    50% {
        transform: scale(1.05);
        filter: drop-shadow(0 6px 16px rgba(24, 85, 147, 0.25));
    }
}

@keyframes title-shimmer {
    0%, 100% {
        opacity: 1;
        text-shadow:
            0 2px 4px rgba(24, 85, 147, 0.2),
            0 4px 8px rgba(24, 85, 147, 0.1);
    }
    50% {
        opacity: 0.9;
        text-shadow:
            0 2px 4px rgba(24, 85, 147, 0.3),
            0 4px 8px rgba(24, 85, 147, 0.2),
            0 0 20px rgba(24, 85, 147, 0.15);
    }
}

/* --- RESPONSIVE STYLES --- */
@media (max-width: 768px) {
    .loader-logo {
        width: 75px;
        height: 75px;
    }

    .loader-title {
        font-size: 1.5rem;
        letter-spacing: 2.5px;
    }

    .loader-container {
        gap: 20px;
        padding-top: 4vh;
    }

    .logo-section {
        gap: 10px;
    }
}

@media (max-width: 480px) {
    .loader-logo {
        width: 65px;
        height: 65px;
    }

    .loader-title {
        font-size: 1.25rem;
        letter-spacing: 2px;
    }

    .loader-container {
        gap: 18px;
        padding-top: 3vh;
    }

    .logo-section {
        gap: 8px;
    }

    .scene {
        width: 100px;
        height: 100px;
    }

    .morph-object {
        width: 50px;
        height: 60px;
    }

    .info-card {
        padding: 12px 24px;
        min-width: 180px;
    }
}
</style>

<script>
(function() {
    'use strict';

    const loader = document.getElementById('pageLoader');
    const shape = document.getElementById('morphShape');
    const base = document.getElementById('coneBase');
    const text = document.getElementById('statusText');
    const dot = document.getElementById('statusDot');

    const STEP_DURATION = 1500; // 1.5 seconds per state
    const MIN_DISPLAY_TIME = 2500; // Minimum display time: 2.5 seconds
    let animationInterval = null;
    const startTime = Date.now(); // Track when loader started

    function runSequence() {
        // --- STEP 1: STOP LIGHT (Start) ---
        setStopLight();

        // --- STEP 2: TRAFFIC CONE (After 1.5s) ---
        setTimeout(() => {
            setCone();
        }, STEP_DURATION);
    }

    function setStopLight() {
        if (!shape) return;
        shape.className = 'morph-object state-light';
        base.classList.remove('visible');

        text.innerText = "LOADING...";
        dot.style.backgroundColor = "#2d2d2d";
        dot.style.boxShadow = "0 0 0 2px rgba(255,255,255,0.5), 0 0 10px #2d2d2d";
    }

    function setCone() {
        if (!shape) return;
        shape.className = 'morph-object state-cone';
        base.classList.add('visible');

        text.innerText = "ALMOST READY";
        dot.style.backgroundColor = "#FF6700";
        dot.style.boxShadow = "0 0 0 2px rgba(255,255,255,0.5), 0 0 10px #FF6700";
    }

    function hideLoader() {
        if (loader) {
            loader.classList.add('loaded');
            // Stop animation
            if (animationInterval) {
                clearInterval(animationInterval);
            }
            // Remove from DOM after transition
            setTimeout(() => {
                if (loader.parentNode) {
                    loader.style.display = 'none';
                }
            }, 500);
        }
    }

    function hideLoaderWithDelay() {
        const elapsedTime = Date.now() - startTime;
        const remainingTime = Math.max(0, MIN_DISPLAY_TIME - elapsedTime);

        // Wait for minimum display time before hiding
        setTimeout(() => {
            hideLoader();
        }, remainingTime);
    }

    // Check if we should show the loader (from login redirect or slow connection)
    const shouldShowLoader = sessionStorage.getItem('showPageLoader') === 'true';

    // Clear the flag immediately
    sessionStorage.removeItem('showPageLoader');

    // If not coming from login and page already loaded, hide immediately
    if (!shouldShowLoader && document.readyState === 'complete') {
        loader.style.display = 'none';
        return;
    }

    // Initialize animation
    runSequence();
    animationInterval = setInterval(runSequence, STEP_DURATION * 2);

    // Hide loader when page is fully loaded (with minimum display time)
    if (document.readyState === 'complete') {
        hideLoaderWithDelay();
    } else {
        window.addEventListener('load', hideLoaderWithDelay);
    }

    // Fallback: Hide after 10 seconds max (in case of slow resources)
    setTimeout(hideLoader, 10000);

    // Expose hide function globally for manual control
    window.hidePageLoader = hideLoader;
})();
</script>
