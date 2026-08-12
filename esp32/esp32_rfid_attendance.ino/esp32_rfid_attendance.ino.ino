/*
 * Tap-and-Go Doorlock - RFID Attendance System
 * COMPLETE WORKING CODE - LOW HEAT VERSION
 * Supports: Residents | Staff | Visitors
 * ENTRY READER (D2) | EXIT READER (D15)
 * WITH LCD DISPLAY, LED INDICATORS, BUZZER
 * WITH PWM CONTROL - REDUCES SOLENOID HEAT
 * Auto-lock after 3 seconds
 */

#include <SPI.h>
#include <MFRC522.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <LiquidCrystal_I2C.h>

// ============================================================
// LCD CONFIGURATION
// ============================================================
LiquidCrystal_I2C lcd(0x27, 16, 2);

// ============================================================
// WIFI CONFIGURATION - UPDATE THESE
// ============================================================
const char* WIFI_SSID = "Albano";
const char* WIFI_PASSWORD = "1234567890@@@";

// ============================================================
// SERVER CONFIGURATION - UPDATE THIS
// ============================================================
const char* SERVER_URL = "http://10.55.160.156/tap-and-go-doorlock/backend/api/v1/rfid_access.php";

// ============================================================
// PIN DEFINITIONS
// ============================================================

// RFID Reader 1 (ENTRY)
#define SS_PIN_1   15   // D2
#define RST_PIN_1  4   // D5

// RFID Reader 2 (EXIT)
#define SS_PIN_2   2  // D15
#define RST_PIN_2  5   // D4

// SPI Pins
#define SCK_PIN    13  // D13
#define MISO_PIN   12  // D12
#define MOSI_PIN   14  // D14

// LEDs
#define LED_RED    16  // D16 - RED LED for DENIED
#define LED_GREEN  17  // D17 - GREEN LED for GRANTED

// Built-in LED for status (WiFi connection)
#define LED_BUILTIN 2

// Buzzer
#define BUZZER_PIN 18  // D18

// ============================================================
// SOLENOID BOLT LOCK PIN
// ============================================================
#define SOLENOID_PIN 23  // D23 - GPIO23 (PWM CAPABLE)

// ============================================================
// SOLENOID LOCK CONFIGURATION - LOW HEAT
// ============================================================
const unsigned long LOCK_OPEN_DURATION = 3000;  // 3 seconds only (reduces heat)
const int SOLENOID_PWM_VALUE = 160;              // 160 = ~63% power (reduces heat)
unsigned long lockOpenTime = 0;
bool isLockOpen = false;

// ============================================================
// CREATE RFID OBJECTS
// ============================================================
MFRC522 rfid1(SS_PIN_1, RST_PIN_1);
MFRC522 rfid2(SS_PIN_2, RST_PIN_2);

// ============================================================
// GLOBAL VARIABLES
// ============================================================
String lastCardUID = "";
unsigned long lastAccessTime = 0;
const unsigned long ACCESS_COOLDOWN = 3000;

// Card storage
struct AuthorizedCard {
    String uid;
    String name;
    String type;
    String room;
    String status;
    String visitorName;
    String purpose;
    String residentVisited;
};

AuthorizedCard authorizedCards[200];
int cardCount = 0;
bool wifiConnected = false;

// LCD timing
unsigned long lcdClearTime = 0;
const unsigned long LCD_DISPLAY_DURATION = 4000;

// LED timing
unsigned long ledOffTime = 0;
const unsigned long LED_DURATION = 2000;

// ============================================================
// SETUP
// ============================================================
void setup() {
    Serial.begin(115200);
    Serial.println("\n==========================================");
    Serial.println("   Tap-and-Go RFID Doorlock System");
    Serial.println("   LOW HEAT MODE - PWM Control");
    Serial.println("   ENTRY: D2  |  EXIT: D15");
    Serial.println("   Auto-lock after 3 seconds");
    Serial.println("==========================================\n");
    
    // LED pins
    pinMode(LED_BUILTIN, OUTPUT);
    digitalWrite(LED_BUILTIN, LOW);
    
    pinMode(LED_RED, OUTPUT);
    pinMode(LED_GREEN, OUTPUT);
    digitalWrite(LED_RED, LOW);
    digitalWrite(LED_GREEN, LOW);
    
    // Buzzer
    pinMode(BUZZER_PIN, OUTPUT);
    digitalWrite(BUZZER_PIN, LOW);
    
    // ============================================================
    // SOLENOID LOCK SETUP - LOW HEAT
    // ============================================================
    pinMode(SOLENOID_PIN, OUTPUT);
    analogWrite(SOLENOID_PIN, 0); // 0 = FULL OFF (Locked)
    Serial.println("🔒 Solenoid Lock initialized (LOW HEAT MODE)");
    Serial.println("   📍 Solenoid Pin: GPIO23 (D23)");
    Serial.println("   📍 PWM Value: " + String(SOLENOID_PWM_VALUE) + " (63% power)");
    Serial.println("   📍 Open Duration: 3 seconds");
    
    // LCD
    lcd.init();
    lcd.backlight();
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Low Heat Mode");
    lcd.setCursor(0, 1);
    lcd.print("Doorlock System");
    delay(2000);
    
    // SPI for RFID
    SPI.begin(SCK_PIN, MISO_PIN, MOSI_PIN, SS_PIN_1);
    
    // Initialize RFID Readers
    rfid1.PCD_Init();
    rfid2.PCD_Init();
    
    Serial.println("RFID Readers initialized:");
    Serial.println("  📍 Reader 1 (ENTRY) - SS: D2, RST: D5");
    Serial.println("  📍 Reader 2 (EXIT)  - SS: D15, RST: D4");
    Serial.println();
    
    // Connect to WiFi
    connectToWiFi();
    
    // Load authorized cards
    loadAuthorizedCards();
    
    // ============================================================
    // TEST RELAY AND SOLENOID - LOW HEAT
    // ============================================================
    Serial.println("\n🔧 Testing Solenoid (Low Heat Mode)...");
    Serial.println("   Listen for CLICK sound from relay");
    
    // Test 1: Locked (0)
    analogWrite(SOLENOID_PIN, 0);
    delay(1000);
    Serial.println("   🔒 LOCKED (0% power)");
    
    // Test 2: Unlock (PWM - reduced power)
    analogWrite(SOLENOID_PIN, SOLENOID_PWM_VALUE);
    delay(1000);
    Serial.println("   🔓 UNLOCKED (" + String(SOLENOID_PWM_VALUE) + " PWM - 63% power)");
    
    // Test 3: Lock again
    analogWrite(SOLENOID_PIN, 0);
    delay(500);
    Serial.println("   🔒 LOCKED (0% power)");
    Serial.println("✅ Solenoid test complete!\n");
    
    // Show ready status
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("System Ready");
    if (wifiConnected) {
        lcd.setCursor(0, 1);
        lcd.print("WiFi Connected");
    } else {
        lcd.setCursor(0, 1);
        lcd.print("WiFi: Offline");
    }
    delay(1500);
    
    // Default display
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Tap-and-Go");
    lcd.setCursor(0, 1);
    lcd.print("Scan your card");
    
    // Blink green LED to show ready
    digitalWrite(LED_GREEN, HIGH);
    delay(300);
    digitalWrite(LED_GREEN, LOW);
    delay(300);
    digitalWrite(LED_GREEN, HIGH);
    delay(300);
    digitalWrite(LED_GREEN, LOW);
    
    Serial.println("\n✅ System Ready!");
    Serial.println("   🔓 Tap authorized card to unlock door (3 seconds)");
    Serial.println("   🔒 Door auto-locks after 3 seconds");
    Serial.println("   🌡️ Low Heat Mode - Solenoid runs at 63% power");
    Serial.println("==========================================\n");
}

// ============================================================
// SOLENOID LOCK CONTROL FUNCTIONS - LOW HEAT
// ============================================================

// Unlock the door with reduced power (PWM)
void unlockDoor() {
    analogWrite(SOLENOID_PIN, SOLENOID_PWM_VALUE); // PWM = less heat
    isLockOpen = true;
    lockOpenTime = millis();
    Serial.println("🔓 DOOR UNLOCKED (63% power - LOW HEAT)");
    
    // Show on LCD
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("🔓 DOOR UNLOCKED");
    lcd.setCursor(0, 1);
    lcd.print("3s auto-lock");
}

// Lock the door - FULL OFF
void lockDoor() {
    analogWrite(SOLENOID_PIN, 0); // 0 = FULL OFF
    isLockOpen = false;
    Serial.println("🔒 DOOR LOCKED (0% power)");
    
    // Reset LCD to idle after a moment
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("🔒 DOOR LOCKED");
    lcd.setCursor(0, 1);
    lcd.print("Auto-lock engaged");
    delay(800);
    
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Tap-and-Go");
    lcd.setCursor(0, 1);
    lcd.print("Scan your card");
}

// Check and auto-lock after 3 seconds
void checkAutoLock() {
    if (isLockOpen && (millis() - lockOpenTime >= LOCK_OPEN_DURATION)) {
        lockDoor();
    }
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
        Serial.println("   📍 IP Address: " + WiFi.localIP().toString());
        wifiConnected = true;
        digitalWrite(LED_BUILTIN, HIGH);
    } else {
        Serial.println("\n❌ WiFi Connection Failed!");
        wifiConnected = false;
    }
}

// ============================================================
// LOAD AUTHORIZED CARDS
// ============================================================
void loadAuthorizedCards() {
    Serial.println("\n📥 Loading authorized cards...");
    
    if (!wifiConnected) {
        Serial.println("   ⚠️ Offline mode");
        return;
    }
    
    HTTPClient http;
    http.begin(SERVER_URL);
    http.addHeader("Content-Type", "application/json");
    
    String payload = "{\"action\":\"get_cards\"}";
    int httpCode = http.POST(payload);
    
    if (httpCode > 0) {
        String response = http.getString();
        Serial.println("   Response received");
        
        StaticJsonDocument<16384> doc;
        DeserializationError error = deserializeJson(doc, response);
        
        if (!error && doc["success"] == true) {
            cardCount = 0;
            JsonArray cards = doc["cards"];
            
            for (JsonVariant card : cards) {
                String uid = card["uid"].as<String>();
                String name = card["user_name"].as<String>();
                String type = card["card_type"].as<String>();
                String room = card["room_number"].as<String>();
                String status = card["status"].as<String>();
                String visitorName = card["visitor_name"].as<String>();
                String purpose = card["purpose_of_visit"].as<String>();
                String residentVisited = card["resident_visited_name"].as<String>();
                
                if (status == "active") {
                    authorizedCards[cardCount].uid = uid;
                    authorizedCards[cardCount].name = name;
                    authorizedCards[cardCount].type = type;
                    authorizedCards[cardCount].room = room;
                    authorizedCards[cardCount].status = status;
                    authorizedCards[cardCount].visitorName = visitorName;
                    authorizedCards[cardCount].purpose = purpose;
                    authorizedCards[cardCount].residentVisited = residentVisited;
                    cardCount++;
                    
                    String displayName = (type == "visitor" && visitorName.length() > 0) ? visitorName : name;
                    Serial.println("   ✅ Card: " + uid + " -> " + displayName + " (" + type + ") Room: " + room);
                    if (type == "visitor") {
                        Serial.println("      👤 Visiting: " + residentVisited + " | Purpose: " + purpose);
                    }
                }
            }
            Serial.println("   📊 Loaded " + String(cardCount) + " active cards");
        } else {
            Serial.println("   ❌ Failed to parse response");
        }
    } else {
        Serial.println("   ❌ HTTP request failed - Code: " + String(httpCode));
    }
    
    http.end();
}

// ============================================================
// CHECK AUTHORIZATION
// ============================================================
bool isAuthorized(String uid) {
    for (int i = 0; i < cardCount; i++) {
        if (authorizedCards[i].uid == uid) {
            return true;
        }
    }
    return false;
}

String getUserName(String uid) {
    for (int i = 0; i < cardCount; i++) {
        if (authorizedCards[i].uid == uid) {
            if (authorizedCards[i].type == "visitor" && authorizedCards[i].visitorName.length() > 0) {
                return authorizedCards[i].visitorName;
            }
            return authorizedCards[i].name;
        }
    }
    return "Unknown";
}

String getUserType(String uid) {
    for (int i = 0; i < cardCount; i++) {
        if (authorizedCards[i].uid == uid) {
            return authorizedCards[i].type;
        }
    }
    return "unknown";
}

String getUserRoom(String uid) {
    for (int i = 0; i < cardCount; i++) {
        if (authorizedCards[i].uid == uid) {
            if (authorizedCards[i].type == "visitor" && authorizedCards[i].residentVisited.length() > 0) {
                return "Visit: " + authorizedCards[i].residentVisited;
            }
            return authorizedCards[i].room;
        }
    }
    return "N/A";
}

String getVisitorPurpose(String uid) {
    for (int i = 0; i < cardCount; i++) {
        if (authorizedCards[i].uid == uid) {
            return authorizedCards[i].purpose;
        }
    }
    return "";
}

// ============================================================
// LED CONTROL FUNCTIONS
// ============================================================
void ledGranted() {
    digitalWrite(LED_RED, LOW);
    digitalWrite(LED_GREEN, HIGH);
    ledOffTime = millis() + LED_DURATION;
}

void ledDenied() {
    digitalWrite(LED_GREEN, LOW);
    digitalWrite(LED_RED, HIGH);
    ledOffTime = millis() + LED_DURATION;
}

void ledOff() {
    digitalWrite(LED_RED, LOW);
    digitalWrite(LED_GREEN, LOW);
}

void blinkGreen(int times, int duration) {
    for (int i = 0; i < times; i++) {
        digitalWrite(LED_GREEN, HIGH);
        delay(duration);
        digitalWrite(LED_GREEN, LOW);
        delay(duration);
    }
}

// ============================================================
// BUZZER FUNCTIONS
// ============================================================
void buzzerGranted() {
    tone(BUZZER_PIN, 1000, 150);
    delay(150);
    noTone(BUZZER_PIN);
    delay(50);
    tone(BUZZER_PIN, 1200, 150);
    delay(150);
    noTone(BUZZER_PIN);
}

void buzzerDenied() {
    for (int i = 0; i < 3; i++) {
        tone(BUZZER_PIN, 400, 100);
        delay(120);
        noTone(BUZZER_PIN);
        delay(80);
    }
}

// ============================================================
// SEND ALERT TO SERVER
// ============================================================
void sendAlert(String uid, String reason) {
    if (!wifiConnected) {
        Serial.println("   ❌ No WiFi - Cannot send alert");
        return;
    }
    
    HTTPClient http;
    http.begin(SERVER_URL);
    http.addHeader("Content-Type", "application/json");
    
    String payload = "{";
    payload += "\"action\":\"send_alert\",";
    payload += "\"uid\":\"" + uid + "\",";
    payload += "\"reason\":\"" + reason + "\",";
    payload += "\"alert_type\":\"unauthorized\"";
    payload += "}";
    
    int httpCode = http.POST(payload);
    
    if (httpCode > 0) {
        Serial.println("   🚨 Alert sent to server!");
    } else {
        Serial.println("   ❌ Failed to send alert - Code: " + String(httpCode));
    }
    
    http.end();
}

// ============================================================
// LCD DISPLAY FUNCTIONS
// ============================================================
void showAccessGranted(String uid, int readerId) {
    String name = getUserName(uid);
    String type = getUserType(uid);
    String room = getUserRoom(uid);
    String purpose = getVisitorPurpose(uid);
    String readerName = (readerId == 1) ? "ENTRY" : "EXIT";
    
    lcd.clear();
    lcd.setCursor(0, 0);
    
    if (type == "visitor") {
        lcd.print("✅ VISITOR");
    } else if (type == "staff") {
        lcd.print("✅ STAFF");
    } else {
        lcd.print("✅ GRANTED");
    }
    lcd.setCursor(8, 0);
    lcd.print(readerName);
    
    String line2 = name;
    if (line2.length() > 16) {
        line2 = line2.substring(0, 13) + "...";
    }
    lcd.setCursor(0, 1);
    lcd.print(line2);
    
    buzzerGranted();
    ledGranted();
    
    // UNLOCK DOOR
    unlockDoor();
    
    // Log access
    String accessType = (readerId == 1) ? "entry" : "exit";
    sendAccessLog(uid, accessType, true);
    
    lcdClearTime = millis() + LCD_DISPLAY_DURATION;
}

void showAccessDenied(String uid, int readerId) {
    String readerName = (readerId == 1) ? "ENTRY" : "EXIT";
    
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("❌ DENIED");
    lcd.setCursor(8, 0);
    lcd.print(readerName);
    lcd.setCursor(0, 1);
    String uidDisplay = "UID: " + uid.substring(0, 8);
    lcd.print(uidDisplay);
    
    buzzerDenied();
    ledDenied();
    
    Serial.println("   🔒 Door remains LOCKED");
    
    String reason = "Unauthorized access attempt with card: " + uid;
    sendAlert(uid, reason);
    
    String accessType = (readerId == 1) ? "entry" : "exit";
    sendAccessLog(uid, accessType, false);
    
    lcdClearTime = millis() + LCD_DISPLAY_DURATION;
}

void showCardDetected(String uid, int readerId) {
    String readerName = (readerId == 1) ? "ENTRY" : "EXIT";
    
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("📇 CARD DETECTED");
    lcd.setCursor(0, 1);
    lcd.print(readerName);
    lcd.setCursor(6, 1);
    String uidDisplay = "UID: " + uid.substring(0, 6);
    lcd.print(uidDisplay);
    
    blinkGreen(1, 100);
    delay(800);
}

void resetLCD() {
    if (millis() - lcdClearTime > 0 && lcdClearTime > 0) {
        if (!isLockOpen) {
            lcd.clear();
            lcd.setCursor(0, 0);
            lcd.print("Tap-and-Go");
            lcd.setCursor(0, 1);
            lcd.print("Scan your card");
        }
        lcdClearTime = 0;
    }
}

// ============================================================
// SEND ACCESS LOG
// ============================================================
void sendAccessLog(String uid, String type, bool granted) {
    if (!wifiConnected) {
        Serial.println("   ❌ No WiFi - Cannot send log");
        return;
    }
    
    HTTPClient http;
    http.begin(SERVER_URL);
    http.addHeader("Content-Type", "application/json");
    
    String payload = "{";
    payload += "\"action\":\"log_access\",";
    payload += "\"uid\":\"" + uid + "\",";
    payload += "\"type\":\"" + type + "\",";
    payload += "\"granted\":" + String(granted ? "true" : "false") + ",";
    payload += "\"power_source\":\"main\"";
    payload += "}";
    
    int httpCode = http.POST(payload);
    
    if (httpCode > 0) {
        Serial.println("   📤 Log sent successfully");
    } else {
        Serial.println("   ❌ HTTP request failed - Code: " + String(httpCode));
    }
    
    http.end();
}

// ============================================================
// READ RFID CARD
// ============================================================
String readCard(MFRC522* rfid) {
    rfid->PCD_Init();
    
    if (!rfid->PICC_IsNewCardPresent()) {
        return "";
    }
    
    if (!rfid->PICC_ReadCardSerial()) {
        return "";
    }
    
    String uid = "";
    for (byte i = 0; i < rfid->uid.size; i++) {
        if (rfid->uid.uidByte[i] < 0x10) {
            uid += "0";
        }
        uid += String(rfid->uid.uidByte[i], HEX);
    }
    uid.toUpperCase();
    
    Serial.print("   📇 Raw UID: ");
    for (byte i = 0; i < rfid->uid.size; i++) {
        if (rfid->uid.uidByte[i] < 0x10) {
            Serial.print("0");
        }
        Serial.print(rfid->uid.uidByte[i], HEX);
    }
    Serial.println();
    
    rfid->PICC_HaltA();
    rfid->PCD_StopCrypto1();
    
    return uid;
}

// ============================================================
// PROCESS CARD
// ============================================================
void processCard(String uid, int readerId, String type) {
    if (uid == lastCardUID && millis() - lastAccessTime < ACCESS_COOLDOWN) {
        Serial.println("   ⏳ Cooldown");
        return;
    }
    
    lastCardUID = uid;
    lastAccessTime = millis();
    
    String readerName = (readerId == 1) ? "ENTRY" : "EXIT";
    Serial.println("   🔍 Card UID: " + uid);
    Serial.println("   📍 Reader: " + String(readerId) + " (" + readerName + ")");
    
    showCardDetected(uid, readerId);
    
    if (isAuthorized(uid)) {
        String userName = getUserName(uid);
        String userType = getUserType(uid);
        String userRoom = getUserRoom(uid);
        String purpose = getVisitorPurpose(uid);
        
        Serial.println("   👤 User: " + userName + " (" + userType + ")");
        
        if (userType == "visitor") {
            Serial.println("   🚪 Visiting: " + userRoom);
            Serial.println("   📝 Purpose: " + purpose);
        } else {
            Serial.println("   🚪 Room: " + userRoom);
        }
        Serial.println("   ✅ Authorized - Access GRANTED");
        Serial.println("   🔓 Unlocking door for 3 seconds...");
        
        showAccessGranted(uid, readerId);
        
        Serial.println("   ✅ " + type + " logged for " + userName);
    } else {
        Serial.println("   ❌ Not Authorized - Access DENIED");
        Serial.println("   🔒 Door remains LOCKED");
        
        showAccessDenied(uid, readerId);
        
        Serial.println("   ❌ " + type + " denied - Alert sent!");
    }
}

// ============================================================
// MAIN LOOP
// ============================================================
void loop() {
    // Reset LCD to idle after display duration
    resetLCD();
    
    // Check and auto-lock door after 3 seconds
    checkAutoLock();
    
    // Turn off LEDs after duration
    if (ledOffTime > 0 && millis() - ledOffTime > 0) {
        ledOff();
        ledOffTime = 0;
    }
    
    // Check reader 1 (ENTRY) - D2
    String uid1 = readCard(&rfid1);
    if (uid1.length() > 0) {
        processCard(uid1, 1, "entry");
    }
    
    // Check reader 2 (EXIT) - D15
    String uid2 = readCard(&rfid2);
    if (uid2.length() > 0 && uid2 != uid1) {
        processCard(uid2, 2, "exit");
    }
    
    // Check WiFi connection periodically
    static unsigned long lastWiFiCheck = 0;
    if (millis() - lastWiFiCheck > 30000) {
        lastWiFiCheck = millis();
        if (WiFi.status() != WL_CONNECTED) {
            wifiConnected = false;
            Serial.println("⚠️ WiFi disconnected. Attempting to reconnect...");
            connectToWiFi();
            if (wifiConnected) {
                loadAuthorizedCards();
            }
        }
    }
    
    delay(50);
}