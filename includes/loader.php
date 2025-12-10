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
    gap: 50px;
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
    background-color: #2d2d2d;
    clip-path: polygon(20% 0%, 20% 100%, 80% 100%, 80% 0%);
    transform: rotate(0deg) scale(1.1);
    box-shadow: 0 15px 30px rgba(0,0,0,0.3);
}

/* The Lights (Red/Yellow/Green) */
.morph-object::before {
    content: '';
    position: absolute;
    inset: 0;
    opacity: 0;
    transition: opacity 0.3s;
    background:
        radial-gradient(circle at 50% 20%, #ff5252 6px, transparent 7px),
        radial-gradient(circle at 50% 50%, #ffeb3b 6px, transparent 7px),
        radial-gradient(circle at 50% 80%, #4caf50 6px, transparent 7px);
}

.morph-object.state-light::before {
    opacity: 1;
    animation: traffic-blink 1s infinite;
}

/* --- STATE 2: TRAFFIC CONE --- */
.morph-object.state-cone {
    background-color: #FF6700;
    clip-path: polygon(25% 15%, 15% 100%, 85% 100%, 75% 15%);
    transform: rotate(0deg) translateY(0);
}

/* Cone Stripes */
.morph-object::after {
    content: '';
    position: absolute;
    inset: 0;
    opacity: 0;
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
    opacity: 1;
}

/* --- CONE BASE --- */
.cone-base {
    position: absolute;
    bottom: 25px;
    width: 70px;
    height: 8px;
    background-color: #FF6700;
    border-radius: 4px;
    z-index: 5;
    transform: scaleX(0);
    opacity: 0;
    transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    box-shadow: 0 5px 10px rgba(0,0,0,0.2);
}

.cone-base.visible {
    transform: scaleX(1);
    opacity: 1;
}

/* --- BADGE --- */
.info-card {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    padding: 12px 24px;
    border-radius: 50px;
    border: 1px solid rgba(255, 255, 255, 0.9);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 180px;
    justify-content: center;
}

.status-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background-color: #4285F4;
    transition: background-color 0.4s;
    box-shadow: 0 0 0 2px rgba(255,255,255,0.5), 0 0 10px currentColor;
}

.status-text {
    font-size: 0.85rem;
    font-weight: 800;
    color: #555;
    letter-spacing: 1px;
    text-transform: uppercase;
}

/* --- ANIMATIONS --- */
@keyframes traffic-blink {
    0%, 100% { filter: brightness(1); }
    50% { filter: brightness(1.2); }
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
