/*
 * Tap-and-Go Doorlock - SLAVE ESP32
 * REGISTRATION SCANNER (Admin Office)
 * 
 * Purpose: Mag-scan ng bagong RFID card para sa registration.
 * Kapag may nag-tap, mag-send ng UID sa server, at makikita ito
 * ng web admin panel (register-rfid.php) via auto-fill.
 * 
 * Simpleng code lang:
 * - 1x RFID Reader
 * - LCD display (optional, pero recommended)
 * - Buzzer (for feedback)
 * - WiFi + HTTP only (walang SPIFFS, walang solenoid)
 * 
 * HARDWARE:
 * - RFID Reader: SS=D2 (GPIO15), RST=D5 (GPIO4)
 * - LCD I2C: SDA=D21, SCL=D22
 * - Buzzer: D18 (GPIO18)
 */

#include <SPI.h>
#include <MFRC522.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <LiquidCrystal_I2C.h>

// ============================================================
// WIFI CONFIGURATION
// ============================================================
const char* WIFI_SSID = "Albano";
const char* WIFI_PASSWORD = "1234567890@@@";

// ============================================================
// SERVER CONFIGURATION - SLAVE ENDPOINT
// ============================================================
const char* SCAN_API_URL = "https://isu-e-ladies-dormitory.up.railway.app/backend/api/get_latest_scan.php";

// ============================================================
// PIN DEFINITIONS
// ============================================================
#define SS_PIN   15   // D2 - RFID SS
#define RST_PIN  4    // D5 - RFID RST

// SPI Pins
#define SCK_PIN  13
#define MISO_PIN 12
#define MOSI_PIN 14

#define BUZZER_PIN 18  // D18
#define LED_BUILTIN 2  // Built-in LED

// ============================================================
// LCD CONFIGURATION
// ============================================================
LiquidCrystal_I2C lcd(0x27, 16, 2);

// ============================================================
// RFID OBJECT
// ============================================================
MFRC522 rfid(SS_PIN, RST_PIN);

// ============================================================
// GLOBAL VARIABLES
// ============================================================
String lastCardUID = "";
unsigned long lastScanTime = 0;
const unsigned long SCAN_COOLDOWN = 3000; // 3 seconds between scans

bool wifiConnected = false;
unsigned long lastWiFiCheck = 0;

// Send interval para hindi mag-spam ng scans
unsigned long lastSendTime = 0;
const unsigned long SEND_COOLDOWN = 2000; // 2 seconds

// Scan counter
int totalScansSent = 0;

// ============================================================
// SETUP
// ============================================================
void setup() {
    Serial.begin(115200);
    delay(1000);
    
    Serial.println("\n==========================================");
    Serial.println("   Tap-and-Go SLAVE Registration Scanner");
    Serial.println("   FOR ADMIN OFFICE USE ONLY");
    Serial.println("==========================================\n");
    
    // LED setup
    pinMode(LED_BUILTIN, OUTPUT);
    digitalWrite(LED_BUILTIN, LOW);
    
    // Buzzer
    pinMode(BUZZER_PIN, OUTPUT);
    digitalWrite(BUZZER_PIN, LOW);
    
    // LCD
    lcd.init();
    lcd.backlight();
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("REGISTRATION");
    lcd.setCursor(0, 1);
    lcd.print("SCANNER v1.0");
    delay(2000);
    
    // SPI + RFID
    SPI.begin(SCK_PIN, MISO_PIN, MOSI_PIN, SS_PIN);
    rfid.PCD_Init();
    Serial.println("📇 RFID Reader initialized (SS: D2, RST: D5)");
    
    // WiFi
    connectToWiFi();
    
    // Ready
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Ready to Scan");
    lcd.setCursor(0, 1);
    if (wifiConnected) {
        lcd.print("WiFi Connected");
    } else {
        lcd.print("Offline Mode");
    }
    delay(1500);
    
    showIdleScreen();
    
    Serial.println("\n✅ Slave Scanner Ready!");
    Serial.println("   📇 Tap card to register");
    Serial.println("   📤 UIDs will be sent to admin panel\n");
}

// ============================================================
// IDLE SCREEN
// ============================================================
void showIdleScreen() {
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Tap Card to");
    lcd.setCursor(0, 1);
    lcd.print("Register");
}

// ============================================================
// WIFI CONNECTION
// ============================================================
void connectToWiFi() {
    Serial.print("📡 Connecting to WiFi");
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 30) {
        delay(500);
        Serial.print(".");
        attempts++;
    }
    
    if (WiFi.status() == WL_CONNECTED) {
        Serial.println("\n✅ WiFi Connected!");
        Serial.println("   📍 IP: " + WiFi.localIP().toString());
        wifiConnected = true;
        digitalWrite(LED_BUILTIN, HIGH);
    } else {
        Serial.println("\n❌ WiFi Failed!");
        wifiConnected = false;
        digitalWrite(LED_BUILTIN, LOW);
    }
}

// ============================================================
// SEND SCAN TO SERVER
// ============================================================
bool sendScanToServer(String uid, int readerId) {
    if (!wifiConnected) {
        Serial.println("   ❌ No WiFi - Cannot send scan");
        return false;
    }
    
    HTTPClient http;
    http.begin(SCAN_API_URL);
    http.addHeader("Content-Type", "application/json");
    http.setTimeout(5000);
    
    String payload = "{";
    payload += "\"action\":\"save_scan\",";
    payload += "\"uid\":\"" + uid + "\",";
    payload += "\"reader_id\":" + String(readerId);
    payload += "}";
    
    Serial.println("   📤 Sending: " + payload);
    
    int httpCode = http.POST(payload);
    
    bool success = false;
    
    if (httpCode > 0) {
        String response = http.getString();
        Serial.println("   📥 Response Code: " + String(httpCode));
        Serial.println("   📥 Response: " + response);
        
        if (httpCode == 200) {
            success = true;
        }
    } else {
        Serial.println("   ❌ Failed - Code: " + String(httpCode));
    }
    
    http.end();
    return success;
}

// ============================================================
// BUZZER FEEDBACK
// ============================================================
void buzzerSuccess() {
    tone(BUZZER_PIN, 1500, 100);
    delay(100);
    noTone(BUZZER_PIN);
    delay(50);
    tone(BUZZER_PIN, 2000, 100);
    delay(100);
    noTone(BUZZER_PIN);
}

void buzzerError() {
    for (int i = 0; i < 2; i++) {
        tone(BUZZER_PIN, 400, 150);
        delay(200);
        noTone(BUZZER_PIN);
        delay(100);
    }
}

// ============================================================
// READ RFID CARD
// ============================================================
String readCard() {
    if (!rfid.PICC_IsNewCardPresent()) {
        return "";
    }
    if (!rfid.PICC_ReadCardSerial()) {
        return "";
    }
    
    String uid = "";
    for (byte i = 0; i < rfid.uid.size; i++) {
        if (rfid.uid.uidByte[i] < 0x10) {
            uid += "0";
        }
        uid += String(rfid.uid.uidByte[i], HEX);
    }
    uid.toUpperCase();
    
    rfid.PICC_HaltA();
    rfid.PCD_StopCrypto1();
    
    return uid;
}

// ============================================================
// PROCESS CARD SCAN
// ============================================================
void processScan(String uid) {
    // Cooldown check
    if (uid == lastCardUID && millis() - lastScanTime < SCAN_COOLDOWN) {
        return;
    }
    
    lastCardUID = uid;
    lastScanTime = millis();
    
    Serial.println("\n📇 Card Detected: " + uid);
    
    // Show on LCD
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Card:");
    lcd.setCursor(0, 1);
    lcd.print(uid);
    
    // Send to server
    bool sent = sendScanToServer(uid, 2); // Reader ID 2 (Slave)
    
    if (sent) {
        totalScansSent++;
        Serial.println("   ✅ Scan sent! Total: " + String(totalScansSent));
        buzzerSuccess();
        
        // Update LCD
        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print("✅ Sent!");
        lcd.setCursor(0, 1);
        lcd.print(uid);
        delay(1500);
    } else {
        Serial.println("   ❌ Failed to send");
        buzzerError();
        
        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print("❌ Failed");
        lcd.setCursor(0, 1);
        lcd.print("Try again");
        delay(2000);
    }
    
    showIdleScreen();
}

// ============================================================
// LOOP
// ============================================================
void loop() {
    unsigned long currentTime = millis();
    
    // Check WiFi every 5 seconds
    if (currentTime - lastWiFiCheck > 5000) {
        lastWiFiCheck = currentTime;
        
        if (WiFi.status() == WL_CONNECTED) {
            if (!wifiConnected) {
                wifiConnected = true;
                digitalWrite(LED_BUILTIN, HIGH);
                Serial.println("✅ WiFi Reconnected");
                
                lcd.clear();
                lcd.setCursor(0, 0);
                lcd.print("WiFi Connected");
                delay(1000);
                showIdleScreen();
            }
        } else {
            if (wifiConnected) {
                wifiConnected = false;
                digitalWrite(LED_BUILTIN, LOW);
                Serial.println("⚠️ WiFi Lost");
                
                lcd.clear();
                lcd.setCursor(0, 0);
                lcd.print("⚠️ No WiFi");
                lcd.setCursor(0, 1);
                lcd.print("Reconnecting...");
                delay(1500);
                showIdleScreen();
            }
        }
    }
    
    // Read RFID card
    String uid = readCard();
    if (uid.length() > 0) {
        processScan(uid);
    }
    
    delay(50);
}
