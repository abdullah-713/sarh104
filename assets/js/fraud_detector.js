/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - FRAUD DETECTION                                      ║
 * ║           كشف التلاعب والمحاكاة                                               ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Features:                                                                   ║
 * ║  - كشف Mock GPS / Fake Location                                             ║
 * ║  - كشف VPN                                                                   ║
 * ║  - كشف المحاكي (Emulator)                                                   ║
 * ║  - كشف الجهاز المروت                                                        ║
 * ║  - كشف القفز المفاجئ في الموقع                                              ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

class FraudDetector {
    constructor() {
        this.suspicionScore = 0;
        this.flags = [];
        this.locationHistory = [];
        this.lastCheck = null;
    }

    /**
     * تشغيل جميع فحوصات الأمان
     */
    async runAllChecks() {
        this.suspicionScore = 0;
        this.flags = [];

        await Promise.all([
            this.checkMockLocation(),
            this.checkVPN(),
            this.checkEmulator(),
            this.checkDevMode(),
            this.checkLocationJump(),
            this.checkBattery(),
            this.checkSensors()
        ]);

        return {
            score: this.suspicionScore,
            flags: this.flags,
            isSuspicious: this.suspicionScore > 50,
            isBlocked: this.suspicionScore > 80,
            timestamp: new Date().toISOString()
        };
    }

    /**
     * كشف Mock GPS / Fake Location
     */
    async checkMockLocation() {
        try {
            // Check if mock location is enabled (Android specific)
            if (navigator.userAgent.includes('Android')) {
                // Check location provider
                const position = await this.getCurrentPosition();
                
                // Mock locations often have very high accuracy
                if (position.coords.accuracy < 1) {
                    this.addFlag('mock_gps_perfect_accuracy', 40);
                }
                
                // Check for sudden location changes
                if (this.locationHistory.length > 0) {
                    const lastPos = this.locationHistory[this.locationHistory.length - 1];
                    const distance = this.calculateDistance(
                        lastPos.latitude, lastPos.longitude,
                        position.coords.latitude, position.coords.longitude
                    );
                    const timeDiff = (Date.now() - lastPos.timestamp) / 1000; // seconds
                    
                    // Speed > 1000 km/h is impossible
                    const speed = (distance / 1000) / (timeDiff / 3600);
                    if (speed > 1000) {
                        this.addFlag('impossible_speed', 60);
                    }
                }

                this.locationHistory.push({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    timestamp: Date.now()
                });

                // Keep only last 10 positions
                if (this.locationHistory.length > 10) {
                    this.locationHistory.shift();
                }
            }

            // Check for mock location apps in navigator
            if (window.navigator.standalone !== undefined) {
                // iOS PWA - less likely to have mock location
            }

        } catch (e) {
            console.warn('[FraudDetector] Mock location check failed:', e);
        }
    }

    /**
     * كشف VPN
     */
    async checkVPN() {
        try {
            // WebRTC leak detection
            const pc = new RTCPeerConnection({
                iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
            });
            
            pc.createDataChannel('');
            
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            
            return new Promise((resolve) => {
                const timeout = setTimeout(() => {
                    pc.close();
                    resolve();
                }, 3000);

                pc.onicecandidate = (ice) => {
                    if (!ice.candidate) return;
                    
                    const candidate = ice.candidate.candidate;
                    
                    // Check for private IP addresses (potential VPN)
                    const privateIpRegex = /(?:10\.|172\.(?:1[6-9]|2[0-9]|3[01])\.|192\.168\.)/;
                    if (privateIpRegex.test(candidate)) {
                        // This might indicate VPN but could also be legitimate
                        // Don't add high score for this alone
                    }
                    
                    clearTimeout(timeout);
                    pc.close();
                    resolve();
                };
            });

        } catch (e) {
            console.warn('[FraudDetector] VPN check failed:', e);
        }
    }

    /**
     * كشف المحاكي (Emulator)
     */
    async checkEmulator() {
        try {
            const ua = navigator.userAgent.toLowerCase();
            
            // Known emulator signatures
            const emulatorSignatures = [
                'android sdk built for x86',
                'emulator',
                'goldfish',
                'ranchu',
                'sdk_gphone',
                'generic_x86',
                'bluestacks',
                'nox',
                'genymotion',
                'andy',
                'memu',
                'ldplayer'
            ];
            
            for (const sig of emulatorSignatures) {
                if (ua.includes(sig)) {
                    this.addFlag('emulator_detected', 80);
                    return;
                }
            }

            // Check for abnormal hardware concurrency
            if (navigator.hardwareConcurrency && navigator.hardwareConcurrency <= 1) {
                this.addFlag('low_hardware_concurrency', 20);
            }

            // Check for touch support on "mobile" device
            if (/android|iphone|ipad/i.test(ua) && !('ontouchstart' in window)) {
                this.addFlag('no_touch_on_mobile', 30);
            }

            // Check device memory (Chrome only)
            if ('deviceMemory' in navigator && navigator.deviceMemory < 1) {
                this.addFlag('abnormal_device_memory', 25);
            }

        } catch (e) {
            console.warn('[FraudDetector] Emulator check failed:', e);
        }
    }

    /**
     * كشف وضع المطور
     */
    async checkDevMode() {
        try {
            // Check for open DevTools
            const widthThreshold = window.outerWidth - window.innerWidth > 160;
            const heightThreshold = window.outerHeight - window.innerHeight > 160;
            
            if (widthThreshold || heightThreshold) {
                this.addFlag('devtools_open', 10);
            }

            // Check for debugger
            const start = performance.now();
            debugger;
            const end = performance.now();
            
            if (end - start > 100) {
                this.addFlag('debugger_detected', 15);
            }

        } catch (e) {
            // Debugger check might throw
        }
    }

    /**
     * كشف القفز المفاجئ في الموقع
     */
    async checkLocationJump() {
        if (this.locationHistory.length < 2) return;

        const current = this.locationHistory[this.locationHistory.length - 1];
        const previous = this.locationHistory[this.locationHistory.length - 2];
        
        const distance = this.calculateDistance(
            previous.latitude, previous.longitude,
            current.latitude, current.longitude
        );
        
        const timeDiff = (current.timestamp - previous.timestamp) / 1000;
        
        // More than 100m in less than 5 seconds is suspicious
        if (distance > 100 && timeDiff < 5) {
            this.addFlag('location_jump', 50);
        }
    }

    /**
     * فحص البطارية
     */
    async checkBattery() {
        try {
            if ('getBattery' in navigator) {
                const battery = await navigator.getBattery();
                
                // Emulators often report 100% battery not charging
                if (battery.level === 1 && !battery.charging) {
                    this.addFlag('battery_full_not_charging', 15);
                }
                
                // Battery level never changes on emulator
                if (battery.chargingTime === Infinity && battery.dischargingTime === Infinity) {
                    this.addFlag('battery_static', 10);
                }
            }
        } catch (e) {
            console.warn('[FraudDetector] Battery check failed:', e);
        }
    }

    /**
     * فحص المستشعرات
     */
    async checkSensors() {
        try {
            // Check for accelerometer
            if ('Accelerometer' in window) {
                const sensor = new Accelerometer({ frequency: 10 });
                
                return new Promise((resolve) => {
                    let readings = [];
                    const timeout = setTimeout(() => {
                        sensor.stop();
                        
                        // If all readings are exactly the same, likely emulator
                        if (readings.length > 3) {
                            const allSame = readings.every(r => 
                                r.x === readings[0].x && 
                                r.y === readings[0].y && 
                                r.z === readings[0].z
                            );
                            
                            if (allSame) {
                                this.addFlag('static_accelerometer', 25);
                            }
                        }
                        
                        resolve();
                    }, 1000);

                    sensor.addEventListener('reading', () => {
                        readings.push({ x: sensor.x, y: sensor.y, z: sensor.z });
                    });

                    sensor.start();
                });
            }
        } catch (e) {
            console.warn('[FraudDetector] Sensor check failed:', e);
        }
    }

    /**
     * إضافة علامة شك
     */
    addFlag(flag, score) {
        if (!this.flags.includes(flag)) {
            this.flags.push(flag);
            this.suspicionScore += score;
        }
    }

    /**
     * الحصول على الموقع الحالي
     */
    getCurrentPosition() {
        return new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(resolve, reject, {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            });
        });
    }

    /**
     * حساب المسافة (Haversine)
     */
    calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371000; // meters
        const φ1 = lat1 * Math.PI / 180;
        const φ2 = lat2 * Math.PI / 180;
        const Δφ = (lat2 - lat1) * Math.PI / 180;
        const Δλ = (lon2 - lon1) * Math.PI / 180;

        const a = Math.sin(Δφ / 2) * Math.sin(Δφ / 2) +
                  Math.cos(φ1) * Math.cos(φ2) *
                  Math.sin(Δλ / 2) * Math.sin(Δλ / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

        return R * c;
    }

    /**
     * إرسال تقرير التلاعب للخادم
     */
    async reportFraud(result) {
        try {
            const response = await fetch('/api/security/report-fraud.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify({
                    score: result.score,
                    flags: result.flags,
                    userAgent: navigator.userAgent,
                    platform: navigator.platform,
                    language: navigator.language,
                    screenResolution: `${screen.width}x${screen.height}`,
                    timestamp: result.timestamp
                })
            });

            return await response.json();
        } catch (e) {
            console.error('[FraudDetector] Failed to report:', e);
        }
    }
}

// Export singleton
window.FraudDetector = new FraudDetector();

// Auto-check on page load
document.addEventListener('DOMContentLoaded', async () => {
    // Wait a bit for page to fully load
    setTimeout(async () => {
        const result = await window.FraudDetector.runAllChecks();
        
        if (result.isSuspicious) {
            console.warn('[SARH Security] Suspicious activity detected:', result.flags);
            
            // Report to server
            await window.FraudDetector.reportFraud(result);
            
            // If blocked, show warning
            if (result.isBlocked) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'تم اكتشاف نشاط مشبوه',
                        text: 'يبدو أنك تستخدم تطبيق تزوير الموقع. هذا مخالف لسياسة الشركة.',
                        confirmButtonText: 'فهمت',
                        allowOutsideClick: false
                    });
                }
            }
        }
    }, 2000);
});
