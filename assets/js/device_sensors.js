/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║           SARH SYSTEM - DEVICE SENSORS COLLECTOR                             ║
 * ║           جامع بيانات الحساسات لكشف المحاكيات                                  ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Version: 1.0.0                                                              ║
 * ║  Purpose: جمع بيانات البطارية، الجيروسكوب، التسارع لكشف التلاعب               ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

class DeviceSensors {
    constructor() {
        this.batteryInfo = null;
        this.gyroscopeData = [];
        this.accelerometerData = [];
        this.motionPermissionGranted = false;
        this.suspicionFlags = [];
        
        this.init();
    }
    
    async init() {
        await this.initBattery();
        await this.initMotionSensors();
        this.detectEmulator();
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // BATTERY API - كشف المحاكيات (البطارية دائماً 100% في المحاكي)
    // ═══════════════════════════════════════════════════════════════════════════
    
    async initBattery() {
        try {
            if ('getBattery' in navigator) {
                const battery = await navigator.getBattery();
                
                this.batteryInfo = {
                    level: Math.round(battery.level * 100),
                    charging: battery.charging,
                    chargingTime: battery.chargingTime,
                    dischargingTime: battery.dischargingTime,
                    timestamp: Date.now()
                };
                
                // مراقبة التغييرات
                battery.addEventListener('levelchange', () => {
                    this.batteryInfo.level = Math.round(battery.level * 100);
                    this.batteryInfo.timestamp = Date.now();
                });
                
                battery.addEventListener('chargingchange', () => {
                    this.batteryInfo.charging = battery.charging;
                    this.batteryInfo.timestamp = Date.now();
                });
                
                // 🚨 كشف المحاكي: بطارية 100% دائماً وغير متصلة بالشاحن
                if (battery.level === 1 && !battery.charging && battery.dischargingTime === Infinity) {
                    this.suspicionFlags.push('battery_emulator_signature');
                }
                
                console.log('[Sensors] Battery initialized:', this.batteryInfo);
            } else {
                console.log('[Sensors] Battery API not supported');
                this.batteryInfo = { supported: false };
            }
        } catch (e) {
            console.warn('[Sensors] Battery API error:', e);
            this.batteryInfo = { error: e.message };
        }
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // MOTION SENSORS - الجيروسكوب والتسارع (المحاكي لا يملك حركة حقيقية)
    // ═══════════════════════════════════════════════════════════════════════════
    
    async initMotionSensors() {
        try {
            // طلب إذن على iOS 13+
            if (typeof DeviceMotionEvent !== 'undefined' && 
                typeof DeviceMotionEvent.requestPermission === 'function') {
                try {
                    const permission = await DeviceMotionEvent.requestPermission();
                    this.motionPermissionGranted = (permission === 'granted');
                } catch (e) {
                    console.log('[Sensors] Motion permission denied');
                }
            } else {
                this.motionPermissionGranted = true;
            }
            
            if (this.motionPermissionGranted) {
                // Gyroscope
                if ('Gyroscope' in window) {
                    try {
                        const gyroscope = new Gyroscope({ frequency: 10 });
                        gyroscope.addEventListener('reading', () => {
                            this.gyroscopeData.push({
                                x: gyroscope.x,
                                y: gyroscope.y,
                                z: gyroscope.z,
                                timestamp: Date.now()
                            });
                            
                            // احتفظ بآخر 50 قراءة فقط
                            if (this.gyroscopeData.length > 50) {
                                this.gyroscopeData.shift();
                            }
                        });
                        gyroscope.start();
                        console.log('[Sensors] Gyroscope initialized');
                    } catch (e) {
                        console.log('[Sensors] Gyroscope not available');
                    }
                }
                
                // Accelerometer
                if ('Accelerometer' in window) {
                    try {
                        const accelerometer = new Accelerometer({ frequency: 10 });
                        accelerometer.addEventListener('reading', () => {
                            this.accelerometerData.push({
                                x: accelerometer.x,
                                y: accelerometer.y,
                                z: accelerometer.z,
                                timestamp: Date.now()
                            });
                            
                            if (this.accelerometerData.length > 50) {
                                this.accelerometerData.shift();
                            }
                        });
                        accelerometer.start();
                        console.log('[Sensors] Accelerometer initialized');
                    } catch (e) {
                        console.log('[Sensors] Accelerometer not available');
                    }
                }
                
                // Fallback: DeviceMotion API
                window.addEventListener('devicemotion', (event) => {
                    if (event.rotationRate) {
                        this.gyroscopeData.push({
                            x: event.rotationRate.alpha || 0,
                            y: event.rotationRate.beta || 0,
                            z: event.rotationRate.gamma || 0,
                            timestamp: Date.now()
                        });
                    }
                    
                    if (event.accelerationIncludingGravity) {
                        this.accelerometerData.push({
                            x: event.accelerationIncludingGravity.x || 0,
                            y: event.accelerationIncludingGravity.y || 0,
                            z: event.accelerationIncludingGravity.z || 0,
                            timestamp: Date.now()
                        });
                    }
                    
                    // تقليم البيانات
                    if (this.gyroscopeData.length > 50) this.gyroscopeData.shift();
                    if (this.accelerometerData.length > 50) this.accelerometerData.shift();
                }, { passive: true });
            }
            
        } catch (e) {
            console.warn('[Sensors] Motion sensors error:', e);
        }
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // EMULATOR DETECTION - كشف المحاكيات
    // ═══════════════════════════════════════════════════════════════════════════
    
    detectEmulator() {
        const checks = {
            // 1. WebGL Renderer (المحاكي عادة SwiftShader أو مشابه)
            webglRenderer: () => {
                try {
                    const canvas = document.createElement('canvas');
                    const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
                    if (gl) {
                        const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
                        if (debugInfo) {
                            const renderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL);
                            const vendor = gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL);
                            
                            // محاكيات معروفة
                            const emulatorSignatures = [
                                'swiftshader', 'llvmpipe', 'mesa', 'vmware',
                                'virtualbox', 'parallels', 'bluestacks',
                                'nox', 'memu', 'genymotion', 'android emulator'
                            ];
                            
                            const combined = (renderer + ' ' + vendor).toLowerCase();
                            for (const sig of emulatorSignatures) {
                                if (combined.includes(sig)) {
                                    return { suspicious: true, reason: `WebGL: ${sig}` };
                                }
                            }
                            
                            return { suspicious: false, renderer, vendor };
                        }
                    }
                } catch (e) {}
                return { suspicious: false };
            },
            
            // 2. Touch Support (المحاكي قد لا يدعم اللمس بشكل صحيح)
            touchSupport: () => {
                const hasTouchPoints = navigator.maxTouchPoints > 0;
                const hasTouchEvent = 'ontouchstart' in window;
                const hasOrientation = 'DeviceOrientationEvent' in window;
                
                // جهاز محمول بدون لمس = مشبوه
                const isMobileUA = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
                if (isMobileUA && !hasTouchPoints) {
                    return { suspicious: true, reason: 'Mobile UA without touch' };
                }
                
                return { suspicious: false, touchPoints: navigator.maxTouchPoints };
            },
            
            // 3. Screen Resolution (دقة غريبة = محاكي)
            screenResolution: () => {
                const { width, height } = screen;
                const ratio = width / height;
                
                // نسب غير طبيعية
                if (ratio < 0.4 || ratio > 2.5) {
                    return { suspicious: true, reason: `Unusual ratio: ${ratio.toFixed(2)}` };
                }
                
                return { suspicious: false, width, height, ratio: ratio.toFixed(2) };
            },
            
            // 4. Timezone vs Geolocation (التوقيت لا يطابق الموقع)
            timezoneConsistency: () => {
                const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                const offset = new Date().getTimezoneOffset();
                
                // نتحقق لاحقاً مع الموقع الجغرافي
                return { suspicious: false, timezone: tz, offset };
            },
            
            // 5. Hardware Concurrency (عدد الأنوية)
            hardwareConcurrency: () => {
                const cores = navigator.hardwareConcurrency || 0;
                // أقل من 2 نواة = مشبوه (محاكي قديم)
                if (cores > 0 && cores < 2) {
                    return { suspicious: true, reason: `Low cores: ${cores}` };
                }
                return { suspicious: false, cores };
            },
            
            // 6. Device Memory
            deviceMemory: () => {
                const memory = navigator.deviceMemory || 0;
                // ذاكرة منخفضة جداً = محاكي
                if (memory > 0 && memory < 1) {
                    return { suspicious: true, reason: `Low memory: ${memory}GB` };
                }
                return { suspicious: false, memory };
            }
        };
        
        // تنفيذ الفحوصات
        for (const [name, check] of Object.entries(checks)) {
            try {
                const result = check();
                if (result.suspicious) {
                    this.suspicionFlags.push(`${name}: ${result.reason}`);
                }
            } catch (e) {
                console.warn(`[Sensors] Check failed: ${name}`, e);
            }
        }
        
        if (this.suspicionFlags.length > 0) {
            console.warn('[Sensors] 🚨 Suspicious device detected:', this.suspicionFlags);
        }
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // ANALYZE MOTION - تحليل الحركة (جهاز ثابت = مشبوه)
    // ═══════════════════════════════════════════════════════════════════════════
    
    analyzeMotion() {
        if (this.gyroscopeData.length < 10) {
            return { hasMotion: null, reason: 'insufficient_data' };
        }
        
        // حساب التباين في الحركة
        const calcVariance = (data, axis) => {
            const values = data.map(d => d[axis]);
            const mean = values.reduce((a, b) => a + b, 0) / values.length;
            const squaredDiffs = values.map(v => Math.pow(v - mean, 2));
            return squaredDiffs.reduce((a, b) => a + b, 0) / values.length;
        };
        
        const varianceX = calcVariance(this.gyroscopeData, 'x');
        const varianceY = calcVariance(this.gyroscopeData, 'y');
        const varianceZ = calcVariance(this.gyroscopeData, 'z');
        
        const totalVariance = varianceX + varianceY + varianceZ;
        
        // جهاز ثابت تماماً لمدة طويلة = مشبوه
        if (totalVariance < 0.0001) {
            return { 
                hasMotion: false, 
                reason: 'device_perfectly_still',
                variance: totalVariance 
            };
        }
        
        return { 
            hasMotion: true, 
            variance: totalVariance,
            samples: this.gyroscopeData.length 
        };
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // GET SENSOR DATA - جمع كل البيانات لإرسالها مع الحضور
    // ═══════════════════════════════════════════════════════════════════════════
    
    getSensorData() {
        const motionAnalysis = this.analyzeMotion();
        
        return {
            battery: this.batteryInfo,
            motion: {
                gyroscope: {
                    samples: this.gyroscopeData.length,
                    latest: this.gyroscopeData.slice(-5)
                },
                accelerometer: {
                    samples: this.accelerometerData.length,
                    latest: this.accelerometerData.slice(-5)
                },
                analysis: motionAnalysis
            },
            suspicionFlags: this.suspicionFlags,
            suspicionScore: this.suspicionFlags.length * 25, // كل علامة = 25 نقطة شك
            deviceInfo: {
                userAgent: navigator.userAgent,
                platform: navigator.platform,
                language: navigator.language,
                cores: navigator.hardwareConcurrency || null,
                memory: navigator.deviceMemory || null,
                touchPoints: navigator.maxTouchPoints || 0,
                screenWidth: screen.width,
                screenHeight: screen.height,
                colorDepth: screen.colorDepth,
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone
            },
            timestamp: Date.now()
        };
    }
    
    // ═══════════════════════════════════════════════════════════════════════════
    // REQUEST PERMISSION - طلب إذن الحساسات (للـ iOS)
    // ═══════════════════════════════════════════════════════════════════════════
    
    async requestPermission() {
        if (typeof DeviceMotionEvent !== 'undefined' && 
            typeof DeviceMotionEvent.requestPermission === 'function') {
            try {
                const permission = await DeviceMotionEvent.requestPermission();
                this.motionPermissionGranted = (permission === 'granted');
                return this.motionPermissionGranted;
            } catch (e) {
                console.warn('[Sensors] Permission request failed:', e);
                return false;
            }
        }
        return true;
    }
}

// إنشاء instance عام
window.deviceSensors = new DeviceSensors();

// تصدير للاستخدام في الملفات الأخرى
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DeviceSensors;
}
