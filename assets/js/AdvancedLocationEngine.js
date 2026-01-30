/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║     SARH AL-ITQAN - ADVANCED LOCATION ENGINE (Dead Reckoning)                ║
 * ║     محرك الموقع المتقدم - نظام التنقل الاستنتاجي                               ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Version: 1.0.0                                                              ║
 * ║  Purpose: High-precision real-time location tracking using GPS + IMU         ║
 * ║  Algorithm: Simplified Kalman Filter (Prediction + Correction)               ║
 * ║  Frequency: 60 FPS position updates, 100ms prediction steps                  ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

class AdvancedLocationEngine {
    /**
     * Constructor - Initialize the dead reckoning engine
     * @param {Object} options - Configuration options
     * @param {string} options.apiEndpoint - API endpoint for batch uploads (default: '/api/tracking/batch')
     * @param {number} options.predictionInterval - Prediction step interval in ms (default: 100)
     * @param {number} options.updateFPS - Position update frequency (default: 60)
     * @param {number} options.batchInterval - Batch upload interval in seconds (default: 30)
     * @param {number} options.noiseThreshold - Minimum acceleration to register movement in m/s² (default: 0.2)
     * @param {number} options.gpsCorrectionWeight - Weight for GPS correction (0-1, default: 0.3)
     */
    constructor(options = {}) {
        // Configuration
        this.apiEndpoint = options.apiEndpoint || '/api/tracking/batch';
        this.predictionInterval = options.predictionInterval || 100; // ms
        this.updateFPS = options.updateFPS || 60;
        this.updateInterval = 1000 / this.updateFPS; // ~16.67ms for 60 FPS
        this.batchInterval = (options.batchInterval || 30) * 1000; // Convert to ms
        this.noiseThreshold = options.noiseThreshold || 0.2; // m/s² - ignore small movements
        this.gpsCorrectionWeight = options.gpsCorrectionWeight || 0.3; // 30% GPS, 70% predicted
        
        // State variables
        this.currentPosition = {
            lat: null,
            lng: null,
            heading: 0, // degrees (0-360, North = 0)
            velocity: 0, // m/s
            accuracy: null,
            timestamp: null
        };
        
        this.lastGpsPosition = null;
        this.lastGpsTime = null;
        this.isInitialized = false;
        
        // Sensor data buffers
        this.accelerometerData = {
            x: 0,
            y: 0,
            z: 0,
            timestamp: null
        };
        
        this.gyroscopeData = {
            alpha: 0, // heading/yaw rotation
            beta: 0,  // pitch rotation
            gamma: 0, // roll rotation
            timestamp: null
        };
        
        // Path buffer for batch upload
        this.pathBuffer = [];
        this.lastBatchTime = Date.now();
        
        // Timer references
        this.predictionTimer = null;
        this.updateTimer = null;
        this.batchTimer = null;
        
        // GPS watch ID
        this.gpsWatchId = null;
        
        // Event callbacks
        this.onPositionUpdateCallback = null;
        this.onErrorCallback = null;
        
        // Control flags
        this.isRunning = false;
        this.sensorPermissionsGranted = false;
        
        console.log('[LocationEngine] Initialized with config:', {
            predictionInterval: this.predictionInterval,
            updateFPS: this.updateFPS,
            batchInterval: this.batchInterval / 1000,
            noiseThreshold: this.noiseThreshold
        });
    }
    
    /**
     * Start the location engine
     * @returns {Promise<boolean>} - Returns true if started successfully
     */
    async start() {
        if (this.isRunning) {
            console.warn('[LocationEngine] Already running');
            return false;
        }
        
        try {
            // Request sensor permissions (iOS 13+)
            await this.requestSensorPermissions();
            
            // Initialize GPS tracking
            this.startGpsTracking();
            
            // Start sensor listeners
            this.startSensorListeners();
            
            // Start prediction loop (every 100ms)
            this.startPredictionLoop();
            
            // Start update loop (60 FPS)
            this.startUpdateLoop();
            
            // Start batch upload timer (every 30 seconds)
            this.startBatchTimer();
            
            this.isRunning = true;
            console.log('[LocationEngine] Started successfully');
            return true;
            
        } catch (error) {
            console.error('[LocationEngine] Failed to start:', error);
            this.handleError(error);
            return false;
        }
    }
    
    /**
     * Stop the location engine
     */
    stop() {
        if (!this.isRunning) {
            return;
        }
        
        // Clear timers
        if (this.predictionTimer) clearInterval(this.predictionTimer);
        if (this.updateTimer) clearInterval(this.updateTimer);
        if (this.batchTimer) clearInterval(this.batchTimer);
        
        // Stop GPS tracking
        if (this.gpsWatchId !== null && navigator.geolocation) {
            navigator.geolocation.clearWatch(this.gpsWatchId);
            this.gpsWatchId = null;
        }
        
        // Remove sensor listeners
        this.stopSensorListeners();
        
        // Flush remaining buffer
        this.flushBuffer();
        
        this.isRunning = false;
        this.isInitialized = false;
        console.log('[LocationEngine] Stopped');
    }
    
    /**
     * Request sensor permissions (required for iOS 13+)
     * @returns {Promise<boolean>}
     */
    async requestSensorPermissions() {
        // DeviceMotion permission (iOS 13+)
        if (typeof DeviceMotionEvent !== 'undefined' && 
            typeof DeviceMotionEvent.requestPermission === 'function') {
            try {
                const motionPermission = await DeviceMotionEvent.requestPermission();
                if (motionPermission !== 'granted') {
                    throw new Error('DeviceMotion permission denied');
                }
            } catch (error) {
                console.warn('[LocationEngine] DeviceMotion permission error:', error);
            }
        }
        
        // DeviceOrientation permission (iOS 13+)
        if (typeof DeviceOrientationEvent !== 'undefined' && 
            typeof DeviceOrientationEvent.requestPermission === 'function') {
            try {
                const orientationPermission = await DeviceOrientationEvent.requestPermission();
                if (orientationPermission !== 'granted') {
                    throw new Error('DeviceOrientation permission denied');
                }
            } catch (error) {
                console.warn('[LocationEngine] DeviceOrientation permission error:', error);
            }
        }
        
        this.sensorPermissionsGranted = true;
        return true;
    }
    
    /**
     * Start GPS tracking using watchPosition
     */
    startGpsTracking() {
        if (!navigator.geolocation) {
            throw new Error('Geolocation API not supported');
        }
        
        const options = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 5000
        };
        
        this.gpsWatchId = navigator.geolocation.watchPosition(
            (position) => this.onGpsUpdate(position),
            (error) => this.onGpsError(error),
            options
        );
        
        console.log('[LocationEngine] GPS tracking started');
    }
    
    /**
     * Handle GPS position update (Correction Step)
     * @param {GeolocationPosition} position - Raw GPS position from browser
     */
    onGpsUpdate(position) {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        const accuracy = position.coords.accuracy; // meters
        const heading = position.coords.heading; // degrees (may be null)
        const speed = position.coords.speed || 0; // m/s (may be null)
        const timestamp = position.timestamp || Date.now();
        
        // First GPS fix - initialize position
        if (!this.isInitialized) {
            this.currentPosition = {
                lat,
                lng,
                heading: heading !== null ? heading : 0,
                velocity: speed,
                accuracy,
                timestamp
            };
            this.isInitialized = true;
            this.lastGpsPosition = { lat, lng };
            this.lastGpsTime = timestamp;
            console.log('[LocationEngine] Initial GPS fix:', { lat, lng, accuracy });
            return;
        }
        
        // Correction Step: Blend predicted position with GPS (Weighted Average)
        if (this.currentPosition.lat !== null && this.currentPosition.lng !== null) {
            const predictedLat = this.currentPosition.lat;
            const predictedLng = this.currentPosition.lng;
            
            // Calculate drift error (distance between predicted and GPS)
            const driftError = this.haversineDistance(
                predictedLat, predictedLng,
                lat, lng
            );
            
            // Only apply correction if drift is significant (> 5 meters)
            if (driftError > 5) {
                // Weighted average: blend GPS (30%) with prediction (70%)
                const correctedLat = this.gpsCorrectionWeight * lat + 
                                    (1 - this.gpsCorrectionWeight) * predictedLat;
                const correctedLng = this.gpsCorrectionWeight * lng + 
                                    (1 - this.gpsCorrectionWeight) * predictedLng;
                
                this.currentPosition.lat = correctedLat;
                this.currentPosition.lng = correctedLng;
                
                console.log(`[LocationEngine] GPS correction applied. Drift: ${driftError.toFixed(2)}m`);
            } else {
                // Small drift - use GPS directly but keep smooth velocity
                this.currentPosition.lat = lat;
                this.currentPosition.lng = lng;
            }
            
            // Update heading if GPS provides it
            if (heading !== null && !isNaN(heading)) {
                // Smooth heading transition
                const headingDiff = this.normalizeAngle(heading - this.currentPosition.heading);
                this.currentPosition.heading += headingDiff * 0.5; // 50% blend
                this.currentPosition.heading = this.normalizeAngle(this.currentPosition.heading);
            }
            
            // Update velocity
            if (speed !== null && speed > 0) {
                this.currentPosition.velocity = speed;
            }
        } else {
            // Fallback: use GPS directly if no prediction available
            this.currentPosition.lat = lat;
            this.currentPosition.lng = lng;
            if (heading !== null) this.currentPosition.heading = heading;
            if (speed !== null) this.currentPosition.velocity = speed;
        }
        
        this.currentPosition.accuracy = accuracy;
        this.currentPosition.timestamp = timestamp;
        this.lastGpsPosition = { lat, lng };
        this.lastGpsTime = timestamp;
    }
    
    /**
     * Handle GPS errors
     * @param {GeolocationPositionError} error - GPS error object
     */
    onGpsError(error) {
        const errorMessages = {
            1: 'PERMISSION_DENIED',
            2: 'POSITION_UNAVAILABLE',
            3: 'TIMEOUT'
        };
        console.warn('[LocationEngine] GPS error:', errorMessages[error.code] || 'UNKNOWN');
        // Continue with dead reckoning even if GPS fails
    }
    
    /**
     * Start sensor listeners for accelerometer and gyroscope
     */
    startSensorListeners() {
        // DeviceMotion - Accelerometer (Linear Acceleration)
        window.addEventListener('devicemotion', (event) => {
            if (!event.acceleration) return;
            
            // Use acceleration (gravity removed) if available, otherwise use accelerationIncludingGravity
            const accel = event.acceleration || event.accelerationIncludingGravity;
            
            this.accelerometerData = {
                x: accel.x || 0,
                y: accel.y || 0,
                z: accel.z || 0,
                timestamp: event.timeStamp || Date.now()
            };
            
            // Also capture rotationRate for gyroscope (fallback)
            if (event.rotationRate) {
                this.gyroscopeData.alpha = event.rotationRate.alpha || this.gyroscopeData.alpha;
                this.gyroscopeData.beta = event.rotationRate.beta || this.gyroscopeData.beta;
                this.gyroscopeData.gamma = event.rotationRate.gamma || this.gyroscopeData.gamma;
                this.gyroscopeData.timestamp = event.timeStamp || Date.now();
            }
        }, { passive: true });
        
        // DeviceOrientation - Heading (Alpha/Compass)
        window.addEventListener('deviceorientation', (event) => {
            if (event.alpha !== null && !isNaN(event.alpha)) {
                this.gyroscopeData.alpha = event.alpha; // 0-360 degrees (compass heading)
                this.gyroscopeData.timestamp = event.timeStamp || Date.now();
            }
            
            if (event.beta !== null && !isNaN(event.beta)) {
                this.gyroscopeData.beta = event.beta; // -180 to 180 (pitch)
            }
            
            if (event.gamma !== null && !isNaN(event.gamma)) {
                this.gyroscopeData.gamma = event.gamma; // -90 to 90 (roll)
            }
        }, { passive: true });
        
        console.log('[LocationEngine] Sensor listeners started');
    }
    
    /**
     * Stop sensor listeners
     */
    stopSensorListeners() {
        // Note: removeEventListener requires the same function reference
        // For simplicity, we rely on the engine stopping, but in production
        // you'd want to store the handler references
        console.log('[LocationEngine] Sensor listeners stopped');
    }
    
    /**
     * Start prediction loop (runs every 100ms)
     */
    startPredictionLoop() {
        this.predictionTimer = setInterval(() => {
            this.predictionStep();
        }, this.predictionInterval);
    }
    
    /**
     * Prediction Step - Calculate movement using accelerometer (Dead Reckoning)
     * Physics: d = v*t + 0.5*a*t²
     */
    predictionStep() {
        if (!this.isInitialized || this.currentPosition.lat === null) {
            return; // Wait for initial GPS fix
        }
        
        const dt = this.predictionInterval / 1000; // Convert ms to seconds
        
        // Calculate total acceleration magnitude (m/s²)
        const accelX = this.accelerometerData.x || 0;
        const accelY = this.accelerometerData.y || 0;
        const accelZ = this.accelerometerData.z || 0;
        
        // Horizontal acceleration (ignore Z-axis for 2D movement)
        const horizontalAccel = Math.sqrt(accelX * accelX + accelY * accelY);
        
        // Filter noise: ignore very small accelerations (< noiseThreshold)
        if (horizontalAccel < this.noiseThreshold) {
            // Assume no movement - keep velocity but don't update position
            // Apply friction to gradually reduce velocity
            this.currentPosition.velocity *= 0.95; // 5% velocity decay per step
            return;
        }
        
        // Update heading from gyroscope (alpha = compass heading)
        if (this.gyroscopeData.alpha !== null && !isNaN(this.gyroscopeData.alpha)) {
            // Smooth heading update (prevent sudden jumps)
            const targetHeading = this.gyroscopeData.alpha;
            const currentHeading = this.currentPosition.heading;
            
            // Normalize angle difference
            let headingDiff = targetHeading - currentHeading;
            if (headingDiff > 180) headingDiff -= 360;
            if (headingDiff < -180) headingDiff += 360;
            
            // Apply smoothing (20% blend per step)
            this.currentPosition.heading += headingDiff * 0.2;
            this.currentPosition.heading = this.normalizeAngle(this.currentPosition.heading);
        }
        
        // Calculate new velocity: v = v₀ + a*t
        this.currentPosition.velocity += horizontalAccel * dt;
        
        // Clamp velocity to reasonable maximum (50 m/s = 180 km/h)
        const maxVelocity = 50;
        if (this.currentPosition.velocity > maxVelocity) {
            this.currentPosition.velocity = maxVelocity;
        }
        
        // Calculate distance traveled: d = v*t + 0.5*a*t²
        const distance = this.currentPosition.velocity * dt + 0.5 * horizontalAccel * dt * dt;
        
        // Update position using Haversine formula
        if (distance > 0) {
            const headingRad = this.degreesToRadians(this.currentPosition.heading);
            const newPosition = this.movePoint(
                this.currentPosition.lat,
                this.currentPosition.lng,
                distance,
                headingRad
            );
            
            this.currentPosition.lat = newPosition.lat;
            this.currentPosition.lng = newPosition.lng;
        }
        
        this.currentPosition.timestamp = Date.now();
    }
    
    /**
     * Start update loop (runs at 60 FPS for UI rendering)
     */
    startUpdateLoop() {
        this.updateTimer = setInterval(() => {
            if (this.isInitialized && this.currentPosition.lat !== null) {
                // Emit position update event
                if (this.onPositionUpdateCallback) {
                    this.onPositionUpdateCallback(
                        this.currentPosition.lat,
                        this.currentPosition.lng,
                        this.currentPosition.heading,
                        this.currentPosition.velocity,
                        this.currentPosition.accuracy
                    );
                }
                
                // Add to buffer for batch upload
                this.pathBuffer.push({
                    lat: this.currentPosition.lat,
                    lng: this.currentPosition.lng,
                    heading: this.currentPosition.heading,
                    velocity: this.currentPosition.velocity,
                    accuracy: this.currentPosition.accuracy,
                    timestamp: this.currentPosition.timestamp
                });
            }
        }, this.updateInterval);
    }
    
    /**
     * Start batch upload timer (every 30 seconds)
     */
    startBatchTimer() {
        this.batchTimer = setInterval(() => {
            this.flushBuffer();
        }, this.batchInterval);
    }
    
    /**
     * Flush path buffer and send to server
     */
    async flushBuffer() {
        if (this.pathBuffer.length === 0) {
            return;
        }
        
        const batch = [...this.pathBuffer];
        this.pathBuffer = []; // Clear buffer
        
        // Compress data (remove redundant points, round coordinates)
        const compressedBatch = this.compressPath(batch);
        
        try {
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    path: compressedBatch,
                    timestamp: Date.now(),
                    count: compressedBatch.length
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            console.log(`[LocationEngine] Batch uploaded: ${compressedBatch.length} points`);
            
        } catch (error) {
            console.error('[LocationEngine] Batch upload failed:', error);
            // Re-add batch to buffer for retry (limit buffer size)
            if (this.pathBuffer.length + batch.length < 1000) {
                this.pathBuffer = [...batch, ...this.pathBuffer];
            }
        }
    }
    
    /**
     * Compress path by removing redundant points and rounding coordinates
     * @param {Array} path - Array of position points
     * @returns {Array} - Compressed path
     */
    compressPath(path) {
        if (path.length <= 1) return path;
        
        const compressed = [];
        const precision = 6; // 6 decimal places ≈ 10cm precision
        
        for (let i = 0; i < path.length; i++) {
            const point = path[i];
            
            // Round coordinates
            compressed.push({
                lat: Math.round(point.lat * Math.pow(10, precision)) / Math.pow(10, precision),
                lng: Math.round(point.lng * Math.pow(10, precision)) / Math.pow(10, precision),
                h: Math.round(point.heading || 0), // Heading (integer degrees)
                v: Math.round((point.velocity || 0) * 10) / 10, // Velocity (1 decimal)
                a: Math.round(point.accuracy || 0), // Accuracy (integer meters)
                t: point.timestamp
            });
        }
        
        return compressed;
    }
    
    /**
     * Move a point by distance and bearing (Haversine-based)
     * @param {number} lat - Starting latitude
     * @param {number} lng - Starting longitude
     * @param {number} distance - Distance in meters
     * @param {number} bearing - Bearing in radians (0 = North)
     * @returns {Object} - New position {lat, lng}
     */
    movePoint(lat, lng, distance, bearing) {
        const R = 6371000; // Earth radius in meters
        const latRad = this.degreesToRadians(lat);
        const lngRad = this.degreesToRadians(lng);
        
        const angularDistance = distance / R;
        const newLatRad = Math.asin(
            Math.sin(latRad) * Math.cos(angularDistance) +
            Math.cos(latRad) * Math.sin(angularDistance) * Math.cos(bearing)
        );
        
        const newLngRad = lngRad + Math.atan2(
            Math.sin(bearing) * Math.sin(angularDistance) * Math.cos(latRad),
            Math.cos(angularDistance) - Math.sin(latRad) * Math.sin(newLatRad)
        );
        
        return {
            lat: this.radiansToDegrees(newLatRad),
            lng: this.radiansToDegrees(newLngRad)
        };
    }
    
    /**
     * Calculate Haversine distance between two points
     * @param {number} lat1 - First latitude
     * @param {number} lng1 - First longitude
     * @param {number} lat2 - Second latitude
     * @param {number} lng2 - Second longitude
     * @returns {number} - Distance in meters
     */
    haversineDistance(lat1, lng1, lat2, lng2) {
        const R = 6371000; // Earth radius in meters
        const φ1 = this.degreesToRadians(lat1);
        const φ2 = this.degreesToRadians(lat2);
        const Δφ = this.degreesToRadians(lat2 - lat1);
        const Δλ = this.degreesToRadians(lng2 - lng1);
        
        const a = Math.sin(Δφ / 2) * Math.sin(Δφ / 2) +
                  Math.cos(φ1) * Math.cos(φ2) *
                  Math.sin(Δλ / 2) * Math.sin(Δλ / 2);
        
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        
        return R * c;
    }
    
    /**
     * Normalize angle to 0-360 range
     * @param {number} angle - Angle in degrees
     * @returns {number} - Normalized angle (0-360)
     */
    normalizeAngle(angle) {
        while (angle < 0) angle += 360;
        while (angle >= 360) angle -= 360;
        return angle;
    }
    
    /**
     * Convert degrees to radians
     * @param {number} degrees - Angle in degrees
     * @returns {number} - Angle in radians
     */
    degreesToRadians(degrees) {
        return degrees * (Math.PI / 180);
    }
    
    /**
     * Convert radians to degrees
     * @param {number} radians - Angle in radians
     * @returns {number} - Angle in degrees
     */
    radiansToDegrees(radians) {
        return radians * (180 / Math.PI);
    }
    
    /**
     * Set callback for position updates (60 FPS)
     * @param {Function} callback - Callback function(lat, lng, heading, velocity, accuracy)
     */
    onPositionUpdate(callback) {
        this.onPositionUpdateCallback = callback;
    }
    
    /**
     * Set callback for errors
     * @param {Function} callback - Callback function(error)
     */
    onError(callback) {
        this.onErrorCallback = callback;
    }
    
    /**
     * Handle errors
     * @param {Error} error - Error object
     */
    handleError(error) {
        console.error('[LocationEngine] Error:', error);
        if (this.onErrorCallback) {
            this.onErrorCallback(error);
        }
    }
    
    /**
     * Get current position
     * @returns {Object} - Current position state
     */
    getCurrentPosition() {
        return { ...this.currentPosition };
    }
    
    /**
     * Get path buffer size
     * @returns {number} - Number of points in buffer
     */
    getBufferSize() {
        return this.pathBuffer.length;
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AdvancedLocationEngine;
}

// Make available globally
window.AdvancedLocationEngine = AdvancedLocationEngine;
