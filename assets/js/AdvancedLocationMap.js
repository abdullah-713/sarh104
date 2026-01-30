/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║     SARH AL-ITQAN - ADVANCED LOCATION MAP RENDERER                           ║
 * ║     عارض الخريطة المتقدم مع حركة السيارة السلسة                              ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Version: 1.0.0                                                              ║
 * ║  Purpose: Smooth 60fps car animation with interpolation & fading trail       ║
 * ║  Integration: AdvancedLocationEngine                                         ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

class AdvancedLocationMap {
    /**
     * Constructor
     * @param {Object} options - Configuration options
     * @param {string|HTMLElement} options.mapContainer - Map container ID or element
     * @param {Object} options.initialCenter - Initial map center {lat, lng}
     * @param {number} options.initialZoom - Initial zoom level (default: 16)
     * @param {AdvancedLocationEngine} options.locationEngine - AdvancedLocationEngine instance
     * @param {number} options.trailDuration - Trail duration in seconds (default: 30)
     * @param {string} options.tileLayer - Tile layer URL (default: OpenStreetMap)
     */
    constructor(options = {}) {
        // Configuration
        this.mapContainer = typeof options.mapContainer === 'string' 
            ? document.getElementById(options.mapContainer) 
            : options.mapContainer;
        
        if (!this.mapContainer) {
            throw new Error('Map container not found');
        }
        
        this.locationEngine = options.locationEngine || null;
        this.trailDuration = (options.trailDuration || 30) * 1000; // Convert to ms
        this.tileLayerUrl = options.tileLayer || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
        this.tileLayerAttribution = options.tileLayerAttribution || '&copy; OpenStreetMap contributors';
        
        // Initial position
        this.initialCenter = options.initialCenter || { lat: 24.7136, lng: 46.6753 };
        this.initialZoom = options.initialZoom || 16;
        
        // State
        this.map = null;
        this.carMarker = null;
        this.trailPolyline = null;
        this.trailPoints = []; // Array of {lat, lng, timestamp}
        
        // Animation state for interpolation
        this.targetPosition = {
            lat: this.initialCenter.lat,
            lng: this.initialCenter.lng,
            heading: 0
        };
        
        this.currentDisplayPosition = {
            lat: this.initialCenter.lat,
            lng: this.initialCenter.lng,
            heading: 0
        };
        
        this.isAnimating = false;
        this.animationFrameId = null;
        
        // Last update timestamp
        this.lastUpdateTime = performance.now();
        
        // Bind methods
        this.animate = this.animate.bind(this);
    }
    
    /**
     * Initialize the map
     */
    init() {
        // Create Leaflet map
        this.map = L.map(this.mapContainer, {
            center: [this.initialCenter.lat, this.initialCenter.lng],
            zoom: this.initialZoom,
            zoomControl: true,
            attributionControl: true
        });
        
        // Add tile layer
        L.tileLayer(this.tileLayerUrl, {
            attribution: this.tileLayerAttribution,
            maxZoom: 19
        }).addTo(this.map);
        
        // Create custom car icon
        const carIcon = this.createCarIcon();
        
        // Create car marker
        this.carMarker = L.marker(
            [this.initialCenter.lat, this.initialCenter.lng],
            { icon: carIcon, rotationAngle: 0 }
        ).addTo(this.map);
        
        // Create trail polyline
        this.trailPolyline = L.polyline([], {
            color: '#FF6B6B',
            weight: 4,
            opacity: 0.6,
            smoothFactor: 1.0
        }).addTo(this.map);
        
        // Initialize display position
        this.currentDisplayPosition.lat = this.initialCenter.lat;
        this.currentDisplayPosition.lng = this.initialCenter.lng;
        this.currentDisplayPosition.heading = 0;
        this.targetPosition = { ...this.currentDisplayPosition };
        
        // Start animation loop
        this.startAnimation();
        
        // Connect to location engine if provided
        if (this.locationEngine) {
            this.locationEngine.onPositionUpdate((lat, lng, heading, velocity, accuracy) => {
                this.updatePosition(lat, lng, heading);
            });
        }
        
        console.log('[LocationMap] Initialized successfully');
    }
    
    /**
     * Create custom car SVG icon
     * @returns {L.Icon} - Leaflet icon instance
     */
    createCarIcon() {
        // Create SVG car icon
        const svg = `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="40" height="40">
                <defs>
                    <filter id="shadow" x="-20%" y="-20%" width="140%" height="140%">
                        <feDropShadow dx="0" dy="2" stdDeviation="3" flood-opacity="0.3"/>
                    </filter>
                </defs>
                <!-- Car body -->
                <rect x="15" y="35" width="70" height="35" rx="5" fill="#4A90E2" filter="url(#shadow)"/>
                <!-- Car roof -->
                <polygon points="25,35 45,15 55,15 75,35" fill="#357ABD" filter="url(#shadow)"/>
                <!-- Windows -->
                <rect x="28" y="20" width="12" height="12" rx="2" fill="#87CEEB" opacity="0.7"/>
                <rect x="60" y="20" width="12" height="12" rx="2" fill="#87CEEB" opacity="0.7"/>
                <!-- Wheels -->
                <circle cx="30" cy="70" r="8" fill="#2C2C2C"/>
                <circle cx="70" cy="70" r="8" fill="#2C2C2C"/>
                <circle cx="30" cy="70" r="5" fill="#555"/>
                <circle cx="70" cy="70" r="5" fill="#555"/>
                <!-- Headlights -->
                <circle cx="20" cy="45" r="3" fill="#FFE135"/>
                <circle cx="80" cy="45" r="3" fill="#FFE135"/>
            </svg>
        `;
        
        const iconUrl = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svg)));
        
        return L.icon({
            iconUrl: iconUrl,
            iconSize: [40, 40],
            iconAnchor: [20, 20], // Center of icon
            popupAnchor: [0, -20],
            className: 'car-marker-icon'
        });
    }
    
    /**
     * Update target position from location engine
     * @param {number} lat - Latitude
     * @param {number} lng - Longitude
     * @param {number} heading - Heading in degrees (0-360)
     */
    updatePosition(lat, lng, heading) {
        if (isNaN(lat) || isNaN(lng)) return;
        
        // Update target position (this will be interpolated smoothly)
        this.targetPosition = {
            lat,
            lng,
            heading: heading || 0
        };
        
        // Add to trail
        this.addTrailPoint(lat, lng);
        
        // Pan map to follow car (smoothly)
        if (this.map) {
            this.map.panTo([lat, lng], {
                animate: true,
                duration: 0.5
            });
        }
    }
    
    /**
     * Add point to trail
     * @param {number} lat - Latitude
     * @param {number} lng - Longitude
     */
    addTrailPoint(lat, lng) {
        const now = Date.now();
        
        this.trailPoints.push({
            lat,
            lng,
            timestamp: now
        });
        
        // Remove old points (older than trailDuration)
        const cutoffTime = now - this.trailDuration;
        this.trailPoints = this.trailPoints.filter(point => point.timestamp > cutoffTime);
        
        // Update trail polyline with fading opacity
        this.updateTrailPolyline();
    }
    
    /**
     * Update trail polyline with gradient opacity (fade over time)
     */
    updateTrailPolyline() {
        if (this.trailPoints.length < 2) {
            this.trailPolyline.setLatLngs([]);
            return;
        }
        
        const now = Date.now();
        const latlngs = [];
        const opacitySteps = 10; // Number of segments for gradient
        
        // For better visual effect, create segments with different opacity
        for (let i = 0; i < this.trailPoints.length - 1; i++) {
            const point1 = this.trailPoints[i];
            const point2 = this.trailPoints[i + 1];
            
            // Calculate age (0 = newest, 1 = oldest)
            const age1 = (now - point1.timestamp) / this.trailDuration;
            const age2 = (now - point2.timestamp) / this.trailDuration;
            
            // Opacity based on age (newer = more opaque)
            const opacity1 = Math.max(0, 1 - age1);
            const opacity2 = Math.max(0, 1 - age2);
            
            // For Leaflet, we'll use a single polyline with average opacity
            // In a more advanced implementation, you could use multiple polylines
            if (i === 0) {
                latlngs.push([point1.lat, point1.lng]);
            }
            latlngs.push([point2.lat, point2.lng]);
        }
        
        // Update polyline
        this.trailPolyline.setLatLngs(latlngs);
        
        // Update opacity based on newest point (simplified gradient)
        const newestAge = (now - this.trailPoints[this.trailPoints.length - 1].timestamp) / this.trailDuration;
        const baseOpacity = Math.max(0.3, 1 - newestAge);
        this.trailPolyline.setStyle({ opacity: baseOpacity * 0.8 });
    }
    
    /**
     * Start animation loop using requestAnimationFrame
     */
    startAnimation() {
        if (this.isAnimating) return;
        
        this.isAnimating = true;
        this.lastUpdateTime = performance.now();
        this.animate();
    }
    
    /**
     * Stop animation loop
     */
    stopAnimation() {
        this.isAnimating = false;
        if (this.animationFrameId) {
            cancelAnimationFrame(this.animationFrameId);
            this.animationFrameId = null;
        }
    }
    
    /**
     * Animation loop - interpolates position smoothly at 60fps
     */
    animate() {
        if (!this.isAnimating) return;
        
        const currentTime = performance.now();
        const deltaTime = (currentTime - this.lastUpdateTime) / 1000; // Convert to seconds
        this.lastUpdateTime = currentTime;
        
        // Interpolation factor (higher = faster, max 1.0 = instant)
        // Using exponential smoothing for smooth movement
        const smoothingFactor = Math.min(1.0, deltaTime * 15); // 15 = speed multiplier
        
        // Interpolate position
        const latDiff = this.targetPosition.lat - this.currentDisplayPosition.lat;
        const lngDiff = this.targetPosition.lng - this.currentDisplayPosition.lng;
        
        this.currentDisplayPosition.lat += latDiff * smoothingFactor;
        this.currentDisplayPosition.lng += lngDiff * smoothingFactor;
        
        // Interpolate heading with angular wrapping
        let headingDiff = this.targetPosition.heading - this.currentDisplayPosition.heading;
        
        // Normalize angle difference (-180 to 180)
        if (headingDiff > 180) headingDiff -= 360;
        if (headingDiff < -180) headingDiff += 360;
        
        this.currentDisplayPosition.heading += headingDiff * smoothingFactor;
        
        // Normalize heading to 0-360
        while (this.currentDisplayPosition.heading < 0) this.currentDisplayPosition.heading += 360;
        while (this.currentDisplayPosition.heading >= 360) this.currentDisplayPosition.heading -= 360;
        
        // Update marker position
        if (this.carMarker) {
            this.carMarker.setLatLng([
                this.currentDisplayPosition.lat,
                this.currentDisplayPosition.lng
            ]);
            
            // Update rotation using CSS transform
            const iconElement = this.carMarker._icon;
            if (iconElement) {
                // Rotate around center (iconAnchor point)
                iconElement.style.transform = `rotate(${this.currentDisplayPosition.heading}deg)`;
                iconElement.style.transition = 'none'; // Disable CSS transitions for smooth animation
            }
        }
        
        // Continue animation loop
        this.animationFrameId = requestAnimationFrame(this.animate);
    }
    
    /**
     * Manually set position (useful for initialization or manual updates)
     * @param {number} lat - Latitude
     * @param {number} lng - Longitude
     * @param {number} heading - Heading in degrees
     */
    setPosition(lat, lng, heading = 0) {
        this.currentDisplayPosition = { lat, lng, heading };
        this.targetPosition = { lat, lng, heading };
        
        if (this.carMarker) {
            this.carMarker.setLatLng([lat, lng]);
            const iconElement = this.carMarker._icon;
            if (iconElement) {
                iconElement.style.transform = `rotate(${heading}deg)`;
            }
        }
    }
    
    /**
     * Center map on car
     * @param {boolean} animate - Whether to animate pan (default: true)
     */
    centerOnCar(animate = true) {
        if (!this.map || !this.carMarker) return;
        
        this.map.setView(
            this.carMarker.getLatLng(),
            this.map.getZoom(),
            { animate, duration: 0.5 }
        );
    }
    
    /**
     * Clear trail
     */
    clearTrail() {
        this.trailPoints = [];
        if (this.trailPolyline) {
            this.trailPolyline.setLatLngs([]);
        }
    }
    
    /**
     * Get current car position
     * @returns {Object} - Current position {lat, lng, heading}
     */
    getCurrentPosition() {
        return { ...this.currentDisplayPosition };
    }
    
    /**
     * Get map instance
     * @returns {L.Map} - Leaflet map instance
     */
    getMap() {
        return this.map;
    }
    
    /**
     * Cleanup - stop animations and remove listeners
     */
    destroy() {
        this.stopAnimation();
        
        if (this.locationEngine) {
            this.locationEngine.onPositionUpdate(null);
        }
        
        if (this.map) {
            this.map.remove();
            this.map = null;
        }
        
        this.carMarker = null;
        this.trailPolyline = null;
        this.trailPoints = [];
        
        console.log('[LocationMap] Destroyed');
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AdvancedLocationMap;
}

// Make available globally
window.AdvancedLocationMap = AdvancedLocationMap;
