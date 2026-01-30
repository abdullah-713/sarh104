/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * USAGE EXAMPLE - Advanced Location Engine + Map Integration
 * ═══════════════════════════════════════════════════════════════════════════════
 * 
 * This example shows how to integrate AdvancedLocationEngine with AdvancedLocationMap
 * for smooth 60fps car tracking with interpolation and fading trail.
 */

// ═══════════════════════════════════════════════════════════════════════════════
// STEP 1: Initialize Location Engine
// ═══════════════════════════════════════════════════════════════════════════════

const locationEngine = new AdvancedLocationEngine({
    apiEndpoint: '/api/tracking/batch',
    predictionInterval: 100,        // 100ms prediction steps
    updateFPS: 60,                  // 60 FPS position updates
    batchInterval: 30,              // Batch upload every 30 seconds
    noiseThreshold: 0.2,            // Ignore movements < 0.2 m/s²
    gpsCorrectionWeight: 0.3        // 30% GPS, 70% dead reckoning
});

// ═══════════════════════════════════════════════════════════════════════════════
// STEP 2: Initialize Map Renderer
// ═══════════════════════════════════════════════════════════════════════════════

const locationMap = new AdvancedLocationMap({
    mapContainer: 'map',                    // HTML element ID
    initialCenter: { lat: 24.7136, lng: 46.6753 },
    initialZoom: 16,
    locationEngine: locationEngine,         // Connect to engine
    trailDuration: 30,                      // Show last 30 seconds of trail
    tileLayer: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'
});

// Initialize map
locationMap.init();

// ═══════════════════════════════════════════════════════════════════════════════
// STEP 3: Start Tracking
// ═══════════════════════════════════════════════════════════════════════════════

async function startTracking() {
    try {
        // Start location engine (this will request sensor permissions)
        const started = await locationEngine.start();
        
        if (started) {
            console.log('✅ Location tracking started');
            
            // Optional: Set error handler
            locationEngine.onError((error) => {
                console.error('Location engine error:', error);
            });
            
            // Optional: Center map on car when position updates
            locationEngine.onPositionUpdate((lat, lng, heading, velocity, accuracy) => {
                // Map automatically receives updates via the locationEngine reference
                // But you can also manually control it here if needed
                console.log(`Position: ${lat.toFixed(6)}, ${lng.toFixed(6)}, Heading: ${heading.toFixed(1)}°`);
            });
            
        } else {
            console.error('❌ Failed to start location tracking');
        }
        
    } catch (error) {
        console.error('Error starting tracking:', error);
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// STEP 4: Optional - Manual Position Updates
// ═══════════════════════════════════════════════════════════════════════════════

// If you need to manually set position (e.g., from GPS watchPosition):
if (navigator.geolocation) {
    navigator.geolocation.watchPosition((position) => {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        const heading = position.coords.heading || 0;
        
        // This will be smoothed by the map's interpolation
        locationMap.updatePosition(lat, lng, heading);
    });
}

// ═══════════════════════════════════════════════════════════════════════════════
// STEP 5: Control Functions
// ═══════════════════════════════════════════════════════════════════════════════

// Center map on car
function centerOnCar() {
    locationMap.centerOnCar(true);
}

// Clear trail
function clearTrail() {
    locationMap.clearTrail();
}

// Get current position
function getCurrentPosition() {
    const pos = locationMap.getCurrentPosition();
    console.log('Current car position:', pos);
    return pos;
}

// Stop tracking
function stopTracking() {
    locationEngine.stop();
    locationMap.stopAnimation();
}

// ═══════════════════════════════════════════════════════════════════════════════
// STEP 6: Cleanup on Page Unload
// ═══════════════════════════════════════════════════════════════════════════════

window.addEventListener('beforeunload', () => {
    locationEngine.stop();
    locationMap.destroy();
});

// ═══════════════════════════════════════════════════════════════════════════════
// HTML EXAMPLE:
// ═══════════════════════════════════════════════════════════════════════════════
/*
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #map {
            width: 100%;
            height: 100vh;
        }
        .car-marker-icon {
            transition: none !important; /* Disable CSS transitions for smooth rotation */
        }
    </style>
</head>
<body>
    <div id="map"></div>
    
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="assets/js/AdvancedLocationEngine.js"></script>
    <script src="assets/js/AdvancedLocationMap.js"></script>
    <script src="assets/js/AdvancedLocationMap.example.js"></script>
    
    <script>
        // Start when page loads
        window.addEventListener('load', () => {
            startTracking();
        });
    </script>
</body>
</html>
*/
