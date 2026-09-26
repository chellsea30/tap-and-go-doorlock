/*
 * Tap-and-Go Doorlock - RFID Attendance System
 * WITH OFFLINE SUPPORT & LOG SYNC
 * Supports: Residents | Staff | Visitors
 * ENTRY READER (D2) | EXIT READER (D15)
 * WITH LCD DISPLAY, LED INDICATORS, BUZZER
 * WITH PWM CONTROL - REDUCES SOLENOID HEAT
 * Auto-lock after 3 seconds
 * OFFLINE CAPABLE - Stores cards and logs locally
 * AUTO-SYNC logs when internet returns
 * ✅ WITH CARD DEACTIVATION SYNC - Auto-refresh when admin changes card status
 * ✅ WITH ONLINE VERIFICATION - Double-check before opening lock
 */

#include <SPI.h>
#include <MFRC522.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <LiquidCrystal_I2C.h>
#include <FS.h>
#include <SPIFFS.h>

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
// SERVER CONFIGURATION - RAILWAY URL
// ============================================================
const char* SERVER_URL = "https://isu-e-ladies-dormitory.up.railway.app/backend/api/v1/rfid_access.php";

// ============================================================
// PIN DEFINITIONS
// ============================================================

// RFID Reader 1 (ENTRY)
#define SS_PIN_1   15   // D2
#define RST_PIN_1  4    // D5

// RFID Reader 2 (EXIT)
#define SS_PIN_2   2    // D15
#define RST_PIN_2  5    // D4

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
const unsigned long LOCK_OPEN_DURATION = 3000;  // 3 seconds
const int SOLENOID_PWM_VALUE = 160;              // ~63% power
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

// Card storage - MAX 200 cards
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

// Offline Log storage
struct OfflineLog {
    String uid;
    String type;
    bool granted;
    String timestamp;
    String userName;
    String userType;
};

AuthorizedCard authorizedCards[200];
OfflineLog offlineLogs[500];
int cardCount = 0;
int logCount = 0;
bool wifiConnected = false;
bool syncInProgress = false;
unsigned long lastSyncAttempt = 0;
const unsigned long SYNC_INTERVAL = 30000; // Sync every 30 seconds

// ============================================================
// ✅ CARD VERSION TRACKING - AUTO-REFRESH SA DEACTIVATE/ACTIVATE
// ============================================================
String lastCardsVersionHash = "";
unsigned long lastVersionCheck = 0;
const unsigned long VERSION_CHECK_INTERVAL = 10000;  // Check every 10 seconds

// LCD timing
unsigned long lcdClearTime = 0;
const unsigned long LCD_DISPLAY_DURATION = 4000;

// LED timing
unsigned long ledOffTime = 0;
const unsigned long LED_DURATION = 2000;

// ============================================================
// FORWARD DECLARATIONS
// ============================================================
void updateLCDIdle();
void saveCardsToSPIFFS();
void loadCardsFromSPIFFS();
void saveOfflineLogsToSPIFFS();
void loadOfflineLogsFromSPIFFS();
void addOfflineLog(String uid, String type, bool granted, String userName, String userType);
void syncOfflineLogs();
void unlockDoor();
void lockDoor();
void checkAutoLock();
void connectToWiFi();
void loadAuthorizedCards();
bool isAuthorized(String uid);
String getUserName(String uid);
String getUserType(String uid);
String getUserRoom(String uid);
String getVisitorPurpose(String uid);
void ledGranted();
void ledDenied();
void ledOff();
void blinkGreen(int times, int duration);
void buzzerGranted();
void buzzerDenied();
void sendAlert(String uid, String reason);
void showAccessGranted(String uid, int readerId);
void showAccessDenied(String uid, int readerId);
void showCardDetected(String uid, int readerId);
void resetLCD();
void sendAccessLog(String uid, String type, bool granted);
String readCard(MFRC522* rfid);
void processCard(String uid, int readerId, String type);
bool verifyCardOnline(String uid);
void checkCardVersionChanges();

// ============================================================
// SETUP
// ============================================================
void setup() {
    Serial.begin(115200);
    Serial.println("\n==========================================");
    Serial.println("   Tap-and-Go RFID Doorlock System");
    Serial.println("   WITH OFFLINE SUPPORT & LOG SYNC");
    Serial.println("   LOW HEAT MODE - PWM Control");
    Serial.println("   ✅ CARD DEACTIVATION SYNC ENABLED");
    Serial.println("   ENTRY: D2  |  EXIT: D15");
    Serial.println("   Auto-lock after 3 seconds");
    Serial.println("==========================================\n");
    
    if (!SPIFFS.begin(true)) {
        Serial.println("⚠️ SPIFFS Mount Failed");
    } else {
        Serial.println("✅ SPIFFS Mounted Successfully");
    }
    
    pinMode(LED_BUILTIN, OUTPUT);
    digitalWrite(LED_BUILTIN, LOW);
    
    pinMode(LED_RED, OUTPUT);
    pinMode(LED_GREEN, OUTPUT);
    digitalWrite(LED_RED, LOW);
    digitalWrite(LED_GREEN, LOW);
    
    pinMode(BUZZER_PIN, OUTPUT);
    digitalWrite(BUZZER_PIN, LOW);
    
    pinMode(SOLENOID_PIN, OUTPUT);
    analogWrite(SOLENOID_PIN, 0);
    Serial.println("🔒 Solenoid Lock initialized (LOW HEAT MODE)");
    
    lcd.init();
    lcd.backlight();
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Low Heat Mode");
    lcd.setCursor(0, 1);
    lcd.print("Doorlock System");
    delay(2000);
    
    SPI.begin(SCK_PIN, MISO_PIN, MOSI_PIN, SS_PIN_1);
    
    rfid1.PCD_Init();
    rfid2.PCD_Init();
    
    Serial.println("RFID Readers initialized:");
    Serial.println("  📍 Reader 1 (ENTRY) - SS: D2, RST: D5");
    Serial.println("  📍 Reader 2 (EXIT)  - SS: D15, RST: D4");
    
    loadCardsFromSPIFFS();
    loadOfflineLogsFromSPIFFS();
    
    connectToWiFi();
    
    if (wifiConnected) {
        loadAuthorizedCards();
        syncOfflineLogs();
    } else {
        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print("📴 OFFLINE MODE");
        lcd.setCursor(0, 1);
        lcd.print(String(cardCount) + " cards loaded");
        delay(2000);
    }
    
    // Test solenoid
    Serial.println("\n🔧 Testing Solenoid (Low Heat Mode)...");
    analogWrite(SOLENOID_PIN, 0);
    delay(1000);
    Serial.println("   🔒 LOCKED (0% power)");
    analogWrite(SOLENOID_PIN, SOLENOID_PWM_VALUE);
    delay(1000);
    Serial.println("   🔓 UNLOCKED (" + String(SOLENOID_PWM_VALUE) + " PWM)");
    analogWrite(SOLENOID_PIN, 0);
    delay(500);
    Serial.println("   🔒 LOCKED (0% power)");
    Serial.println("✅ Solenoid test complete!\n");
    
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("System Ready");
    if (wifiConnected) {
        lcd.setCursor(0, 1);
        lcd.print("WiFi Connected");
    } else {
        lcd.setCursor(0, 1);
        lcd.print("📴 Offline Mode");
    }
    delay(1500);
    
    updateLCDIdle();
    
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
    Serial.println("   📴 OFFLINE SUPPORT: " + String(cardCount) + " cards stored locally");
    Serial.println("   📝 PENDING LOGS: " + String(logCount) + " logs to sync");
    Serial.println("   🔄 AUTO-SYNC: Card changes detected every 10s");
    Serial.println("==========================================\n");
}

// ============================================================
// LCD UPDATE FUNCTIONS
// ============================================================
void updateLCDIdle() {
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Tap-and-Go");
    lcd.setCursor(0, 1);
    if (wifiConnected) {
        lcd.print("Scan your card");
    } else {
        lcd.print("📴 Offline: " + String(cardCount) + " cards");
        if (logCount > 0) {
            lcd.setCursor(14, 1);
            lcd.print("📝");
        }
    }
}

// ============================================================
// SPIFFS FUNCTIONS - CARDS
// ============================================================
void saveCardsToSPIFFS() {
    File file = SPIFFS.open("/cards.txt", "w");
    if (!file) {
        Serial.println("❌ Failed to open cards.txt for writing");
        return;
    }
    
    file.println(cardCount);
    for (int i = 0; i < cardCount; i++) {
        file.println(authorizedCards[i].uid);
        file.println(authorizedCards[i].name);
        file.println(authorizedCards[i].type);
        file.println(authorizedCards[i].room);
        file.println(authorizedCards[i].status);
        file.println(authorizedCards[i].visitorName);
        file.println(authorizedCards[i].purpose);
        file.println(authorizedCards[i].residentVisited);
    }
    
    file.close();
    Serial.println("✅ Cards saved to SPIFFS (" + String(cardCount) + " cards)");
}

void loadCardsFromSPIFFS() {
    if (!SPIFFS.exists("/cards.txt")) {
        Serial.println("⚠️ No saved cards found in SPIFFS");
        return;
    }
    
    File file = SPIFFS.open("/cards.txt", "r");
    if (!file) {
        Serial.println("❌ Failed to open cards.txt for reading");
        return;
    }
    
    if (file.available()) {
        cardCount = file.readStringUntil('\n').toInt();
    }
    
    for (int i = 0; i < cardCount && i < 200; i++) {
        if (file.available()) authorizedCards[i].uid = file.readStringUntil('\n');
        if (file.available()) authorizedCards[i].name = file.readStringUntil('\n');
        if (file.available()) authorizedCards[i].type = file.readStringUntil('\n');
        if (file.available()) authorizedCards[i].room = file.readStringUntil('\n');
        if (file.available()) authorizedCards[i].status = file.readStringUntil('\n');
        if (file.available()) authorizedCards[i].visitorName = file.readStringUntil('\n');
        if (file.available()) authorizedCards[i].purpose = file.readStringUntil('\n');
        if (file.available()) authorizedCards[i].residentVisited = file.readStringUntil('\n');
        
        authorizedCards[i].uid.trim();
        authorizedCards[i].name.trim();
        authorizedCards[i].type.trim();
        authorizedCards[i].room.trim();
        authorizedCards[i].status.trim();
        authorizedCards[i].visitorName.trim();
        authorizedCards[i].purpose.trim();
        authorizedCards[i].residentVisited.trim();
    }
    
    file.close();
    Serial.println("✅ Loaded " + String(cardCount) + " cards from SPIFFS");
}

// ============================================================
// SPIFFS FUNCTIONS - OFFLINE LOGS
// ============================================================
void saveOfflineLogsToSPIFFS() {
    File file = SPIFFS.open("/logs.txt", "w");
    if (!file) {
        Serial.println("❌ Failed to open logs.txt for writing");
        return;
    }
    
    file.println(logCount);
    for (int i = 0; i < logCount; i++) {
        file.println(offlineLogs[i].uid);
        file.println(offlineLogs[i].type);
        file.println(offlineLogs[i].granted ? "1" : "0");
        file.println(offlineLogs[i].timestamp);
        file.println(offlineLogs[i].userName);
        file.println(offlineLogs[i].userType);
    }
    
    file.close();
    Serial.println("✅ " + String(logCount) + " offline logs saved to SPIFFS");
}

void loadOfflineLogsFromSPIFFS() {
    if (!SPIFFS.exists("/logs.txt")) {
        Serial.println("⚠️ No saved logs found in SPIFFS");
        return;
    }
    
    File file = SPIFFS.open("/logs.txt", "r");
    if (!file) {
        Serial.println("❌ Failed to open logs.txt for reading");
        return;
    }
    
    if (file.available()) {
        logCount = file.readStringUntil('\n').toInt();
    }
    
    for (int i = 0; i < logCount && i < 500; i++) {
        if (file.available()) offlineLogs[i].uid = file.readStringUntil('\n');
        if (file.available()) offlineLogs[i].type = file.readStringUntil('\n');
        if (file.available()) offlineLogs[i].granted = (file.readStringUntil('\n').toInt() == 1);
        if (file.available()) offlineLogs[i].timestamp = file.readStringUntil('\n');
        if (file.available()) offlineLogs[i].userName = file.readStringUntil('\n');
        if (file.available()) offlineLogs[i].userType = file.readStringUntil('\n');
        
        offlineLogs[i].uid.trim();
        offlineLogs[i].type.trim();
        offlineLogs[i].timestamp.trim();
        offlineLogs[i].userName.trim();
        offlineLogs[i].userType.trim();
    }
    
    file.close();
    Serial.println("✅ Loaded " + String(logCount) + " offline logs from SPIFFS");
    
    if (logCount > 0) {
        Serial.println("   📝 Pending logs to sync: " + String(logCount));
    }
}

// ============================================================
// ADD OFFLINE LOG
// ============================================================
void addOfflineLog(String uid, String type, bool granted, String userName, String userType) {
    if (logCount >= 500) {
        for (int i = 0; i < 499; i++) {
            offlineLogs[i] = offlineLogs[i + 1];
        }
        logCount = 499;
        Serial.println("   ⚠️ Log buffer full - removed oldest log");
    }
    
    offlineLogs[logCount].uid = uid;
    offlineLogs[logCount].type = type;
    offlineLogs[logCount].granted = granted;
    offlineLogs[logCount].userName = userName;
    offlineLogs[logCount].userType = userType;
    
    time_t now = time(nullptr);
    if (now > 0) {
        struct tm timeinfo;
        gmtime_r(&now, &timeinfo);
        char buffer[30];
        strftime(buffer, sizeof(buffer), "%Y-%m-%d %H:%M:%S", &timeinfo);
        offlineLogs[logCount].timestamp = String(buffer);
    } else {
        offlineLogs[logCount].timestamp = "Offline: " + String(millis() / 1000);
    }
    
    logCount++;
    saveOfflineLogsToSPIFFS();
    Serial.println("   📝 Log saved locally (pending sync) - " + String(logCount) + " logs total");
}

// ============================================================
// SYNC OFFLINE LOGS TO SERVER
// ============================================================
void syncOfflineLogs() {
    if (!wifiConnected) {
        Serial.println("   ⚠️ No WiFi - Cannot sync logs");
        return;
    }
    
    if (logCount == 0) {
        Serial.println("   ✅ No pending logs to sync");
        return;
    }
    
    if (syncInProgress) {
        Serial.println("   ⏳ Sync already in progress...");
        return;
    }
    
    syncInProgress = true;
    Serial.println("📤 Syncing " + String(logCount) + " offline logs to server...");
    
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("📤 Syncing logs...");
    lcd.setCursor(0, 1);
    lcd.print(String(logCount) + " logs pending");
    
    int syncedCount = 0;
    int failedCount = 0;
    
    for (int i = 0; i < logCount; i++) {
        HTTPClient http;
        http.begin(SERVER_URL);
        http.addHeader("Content-Type", "application/json");
        http.setTimeout(5000);
        
        String payload = "{";
        payload += "\"action\":\"log_access\",";
        payload += "\"uid\":\"" + offlineLogs[i].uid + "\",";
        payload += "\"type\":\"" + offlineLogs[i].type + "\",";
        payload += "\"granted\":" + String(offlineLogs[i].granted ? "true" : "false") + ",";
        payload += "\"timestamp\":\"" + offlineLogs[i].timestamp + "\",";
        payload += "\"user_name\":\"" + offlineLogs[i].userName + "\",";
        payload += "\"user_type\":\"" + offlineLogs[i].userType + "\",";
        payload += "\"power_source\":\"offline_sync\"";
        payload += "}";
        
        int httpCode = http.POST(payload);
        
        if (httpCode > 0) {
            Serial.println("   ✅ Synced log " + String(i+1) + "/" + String(logCount) + " - UID: " + offlineLogs[i].uid);
            syncedCount++;
        } else {
            Serial.println("   ❌ Failed to sync log " + String(i+1) + " - Code: " + String(httpCode));
            failedCount++;
        }
        
        http.end();
        delay(50);
    }
    
    if (failedCount == 0 && syncedCount > 0) {
        logCount = 0;
        saveOfflineLogsToSPIFFS();
        Serial.println("✅ All " + String(syncedCount) + " logs synced and cleared!");
        
        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print("✅ Sync Complete");
        lcd.setCursor(0, 1);
        lcd.print(String(syncedCount) + " logs sent");
        delay(1500);
    } else if (failedCount > 0) {
        saveOfflineLogsToSPIFFS();
        Serial.println("⚠️ " + String(failedCount) + " logs failed - will retry next sync");
        
        lcd.clear();
        lcd.setCursor(0, 0);
        lcd.print("⚠️ " + String(failedCount) + " logs");
        lcd.setCursor(0, 1);
        lcd.print("failed - retrying");
        delay(1500);
    }
    
    syncInProgress = false;
    updateLCDIdle();
}

// ============================================================
// SOLENOID LOCK CONTROL FUNCTIONS
// ============================================================
void unlockDoor() {
    analogWrite(SOLENOID_PIN, SOLENOID_PWM_VALUE);
    isLockOpen = true;
    lockOpenTime = millis();
    Serial.println("🔓 DOOR UNLOCKED (63% power - LOW HEAT)");
    
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("🔓 DOOR UNLOCKED");
    lcd.setCursor(0, 1);
    lcd.print("3s auto-lock");
}

void lockDoor() {
    analogWrite(SOLENOID_PIN, 0);
    isLockOpen = false;
    Serial.println("🔒 DOOR LOCKED (0% power)");
    
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("🔒 DOOR LOCKED");
    lcd.setCursor(0, 1);
    lcd.print("Auto-lock engaged");
    delay(800);
    
    updateLCDIdle();
}

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
        digitalWrite(LED_BUILTIN, LOW);
    }
}

// ============================================================
// ✅ VERIFY CARD ONLINE - BAGO MAG-OPEN NG LOCK
// ============================================================
bool verifyCardOnline(String uid) {
    if (!wifiConnected) {
        return true;  // Fallback sa offline check
    }
    
    HTTPClient http;
    http.begin(SERVER_URL);
    http.addHeader("Content-Type", "application/json");
    http.setTimeout(3000);
    
    String payload = "{\"action\":\"check_card_status\",\"uid\":\"" + uid + "\"}";
    int httpCode = http.POST(payload);
    
    bool is_active = false;
    
    if (httpCode > 0) {
        String response = http.getString();
        StaticJsonDocument<1024> doc;
        DeserializationError error = deserializeJson(doc, response);
        
        if (!error && doc["success"] == true) {
            is_active = doc["is_active"].as<bool>();
            String status = doc["status"].as<String>();
            
            Serial.println("   🔍 Online Check: " + uid + " → Status: " + status);
            
            if (!is_active) {
                Serial.println("   ⚠️ Card is INACTIVE on server - access should be DENIED");
            }
        }
    } else {
        Serial.println("   ⚠️ Online check failed (Code: " + String(httpCode) + ") - using offline data");
        http.end();
        return true;
    }
    
    http.end();
    return is_active;
}

// ============================================================
// ✅ CHECK FOR CARD VERSION CHANGES - AUTO REFRESH
// ============================================================
void checkCardVersionChanges() {
    if (!wifiConnected) return;
    if (syncInProgress) return;
    
    HTTPClient http;
    http.begin(SERVER_URL);
    http.addHeader("Content-Type", "application/json");
    http.setTimeout(5000);
    
    String payload = "{\"action\":\"get_cards\"}";
    int httpCode = http.POST(payload);
    
    if (httpCode > 0) {
        String response = http.getString();
        StaticJsonDocument<16384> doc;
        DeserializationError error = deserializeJson(doc, response);
        
        if (!error && doc["success"] == true) {
            String newVersionHash = doc["version_hash"].as<String>();
            
            if (lastCardsVersionHash != "" && newVersionHash != lastCardsVersionHash) {
                Serial.println("🔄 Card changes detected! Refreshing...");
                Serial.println("   Old version: " + lastCardsVersionHash);
                Serial.println("   New version: " + newVersionHash);
                
                cardCount = 0;
                JsonArray cards = doc["cards"];
                
                for (JsonVariant card : cards) {
                    String uid = card["uid"].as<String>();
                    uid.toUpperCase();
                    
                    authorizedCards[cardCount].uid = uid;
                    authorizedCards[cardCount].name = card["user_name"].as<String>();
                    authorizedCards[cardCount].type = card["card_type"].as<String>();
                    authorizedCards[cardCount].room = card["room_number"].as<String>();
                    authorizedCards[cardCount].status = "active";
                    authorizedCards[cardCount].visitorName = card["visitor_name"].as<String>();
                    authorizedCards[cardCount].purpose = card["purpose_of_visit"].as<String>();
                    authorizedCards[cardCount].residentVisited = card["resident_visited_name"].as<String>();
                    cardCount++;
                }
                
                saveCardsToSPIFFS();
                
                Serial.println("✅ Cards refreshed! Total: " + String(cardCount));
                
                lcd.clear();
                lcd.setCursor(0, 0);
                lcd.print("🔄 Cards Updated");
                lcd.setCursor(0, 1);
                lcd.print(String(cardCount) + " active cards");
                delay(1500);
                updateLCDIdle();
            }
            
            lastCardsVersionHash = newVersionHash;
        }
    }
    
    http.end();
}

// ============================================================
// LOAD AUTHORIZED CARDS FROM SERVER
// ============================================================
void loadAuthorizedCards() {
    if (!wifiConnected) {
        Serial.println("   ⚠️ No WiFi - Using offline cards");
        return;
    }
    
    if (syncInProgress) {
        Serial.println("   ⏳ Sync already in progress...");
        return;
    }
    
    Serial.println("\n📥 Loading authorized cards from server...");
    
    HTTPClient http;
    http.begin(SERVER_URL);
    http.addHeader("Content-Type", "application/json");
    http.setTimeout(10000);
    
    String payload = "{\"action\":\"get_cards\"}";
    int httpCode = http.POST(payload);
    
    if (httpCode > 0) {
        String response = http.getString();
        Serial.println("   Response received (Code: " + String(httpCode) + ")");
        
        StaticJsonDocument<16384> doc;
        DeserializationError error = deserializeJson(doc, response);
        
        if (!error && doc["success"] == true) {
            cardCount = 0;
            JsonArray cards = doc["cards"];
            
            for (JsonVariant card : cards) {
                String uid = card["uid"].as<String>();
                uid.toUpperCase();
                
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
                }
            }
            Serial.println("   📊 Loaded " + String(cardCount) + " active cards from server");
            
            // ✅ I-save ang version hash
            if (doc.containsKey("version_hash")) {
                lastCardsVersionHash = doc["version_hash"].as<String>();
                Serial.println("   🏷️ Version Hash: " + lastCardsVersionHash);
            }
            
            saveCardsToSPIFFS();
            
            lcd.clear();
            lcd.setCursor(0, 0);
            lcd.print("✅ Sync Complete");
            lcd.setCursor(0, 1);
            lcd.print(String(cardCount) + " cards loaded");
            delay(1500);
            
            updateLCDIdle();
        } else {
            Serial.println("   ❌ Failed to parse response");
        }
    } else {
        Serial.println("   ❌ HTTP request failed - Code: " + String(httpCode));
        Serial.println("   ⚠️ Using offline cards from SPIFFS");
    }
    
    http.end();
}

// ============================================================
// CHECK AUTHORIZATION
// ============================================================
bool isAuthorized(String uid) {
    uid.toUpperCase();
    for (int i = 0; i < cardCount; i++) {
        if (authorizedCards[i].uid == uid) {
            return true;
        }
    }
    return false;
}

String getUserName(String uid) {
    uid.toUpperCase();
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
    uid.toUpperCase();
    for (int i = 0; i < cardCount; i++) {
        if (authorizedCards[i].uid == uid) {
            return authorizedCards[i].type;
        }
    }
    return "unknown";
}

String getUserRoom(String uid) {
    uid.toUpperCase();
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
    uid.toUpperCase();
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
        Serial.println("   ❌ No WiFi - Alert saved locally as log");
        return;
    }
    
    HTTPClient http;
    http.begin(SERVER_URL);
    http.addHeader("Content-Type", "application/json");
    http.setTimeout(5000);
    
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
    
    unlockDoor();
    
    String accessType = (readerId == 1) ? "entry" : "exit";
    
    if (wifiConnected) {
        sendAccessLog(uid, accessType, true);
    } else {
        addOfflineLog(uid, accessType, true, name, type);
        Serial.println("   📝 Log saved OFFLINE (will sync later)");
    }
    
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
    
    if (wifiConnected) {
        sendAccessLog(uid, accessType, false);
    } else {
        addOfflineLog(uid, accessType, false, "Unknown", "unauthorized");
        Serial.println("   📝 Denied log saved OFFLINE (will sync later)");
    }
    
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
            updateLCDIdle();
        }
        lcdClearTime = 0;
    }
}

// ============================================================
// SEND ACCESS LOG TO SERVER
// ============================================================
void sendAccessLog(String uid, String type, bool granted) {
    if (!wifiConnected) {
        Serial.println("   ❌ No WiFi - Cannot send log");
        return;
    }
    
    HTTPClient http;
    http.begin(SERVER_URL);
    http.addHeader("Content-Type", "application/json");
    http.setTimeout(5000);
    
    String payload = "{";
    payload += "\"action\":\"log_access\",";
    payload += "\"uid\":\"" + uid + "\",";
    payload += "\"type\":\"" + type + "\",";
    payload += "\"granted\":" + String(granted ? "true" : "false") + ",";
    payload += "\"power_source\":\"online\"";
    payload += "}";
    
    int httpCode = http.POST(payload);
    
    if (httpCode > 0) {
        Serial.println("   📤 Log sent successfully");
    } else {
        Serial.println("   ❌ HTTP request failed - Code: " + String(httpCode));
        Serial.println("   📝 Saving log locally instead");
        
        String userName = getUserName(uid);
        String userType = getUserType(uid);
        addOfflineLog(uid, type, granted, userName, userType);
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
// ✅ PROCESS CARD - WITH ONLINE VERIFICATION
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
    
    // ------------------------------------------------------------
    // STEP 1: Check locally (offline data)
    // ------------------------------------------------------------
    bool localAuthorized = isAuthorized(uid);
    
    // ------------------------------------------------------------
    // STEP 2: If online, verify with server (real-time)
    // ------------------------------------------------------------
    bool onlineAuthorized = true;
    if (wifiConnected) {
        onlineAuthorized = verifyCardOnline(uid);
    }
    
    // ------------------------------------------------------------
    // STEP 3: Both checks must pass
    // ------------------------------------------------------------
    bool finalAuthorized = localAuthorized && onlineAuthorized;
    
    if (finalAuthorized) {
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
        // Determine kung bakit denied
        String denyReason = "";
        if (!localAuthorized && !onlineAuthorized) {
            denyReason = "Not in local AND server says inactive";
        } else if (!localAuthorized) {
            denyReason = "Not in local cards (removed from server)";
        } else {
            denyReason = "Server says card is DEACTIVATED/EXPIRED";
        }
        
        Serial.println("   ❌ Access DENIED - Reason: " + denyReason);
        Serial.println("   🔒 Door remains LOCKED");
        
        showAccessDenied(uid, readerId);
        
        Serial.println("   ❌ " + type + " denied - Alert sent!");
    }
}

// ============================================================
// MAIN LOOP
// ============================================================
void loop() {
    resetLCD();
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
    
    // WiFi and Sync Management
    static unsigned long lastWiFiCheck = 0;
    unsigned long currentTime = millis();
    
    // Check WiFi every 5 seconds
    if (currentTime - lastWiFiCheck > 5000) {
        lastWiFiCheck = currentTime;
        
        if (WiFi.status() == WL_CONNECTED) {
            if (!wifiConnected) {
                wifiConnected = true;
                digitalWrite(LED_BUILTIN, HIGH);
                Serial.println("✅ WiFi Reconnected!");
                
                if (logCount > 0) {
                    Serial.println("🔄 Syncing " + String(logCount) + " offline logs...");
                    syncOfflineLogs();
                }
                
                Serial.println("🔄 Refreshing authorized cards...");
                loadAuthorizedCards();
                checkCardVersionChanges();
                
                updateLCDIdle();
            }
        } else {
            if (wifiConnected) {
                wifiConnected = false;
                digitalWrite(LED_BUILTIN, LOW); 
                Serial.println("⚠️ WiFi Lost - Switching to OFFLINE MODE");
                Serial.println("   Using " + String(cardCount) + " saved cards");
                Serial.println("   📝 " + String(logCount) + " logs pending sync");
                updateLCDIdle();
            }
        }
    }
    
    // ✅ Check for card version changes every 10 seconds
    if (wifiConnected && !syncInProgress) {
        if (currentTime - lastVersionCheck > VERSION_CHECK_INTERVAL) {
            lastVersionCheck = currentTime;
            checkCardVersionChanges();
        }
    }
    
    // Periodic sync every SYNC_INTERVAL if online and logs are pending
    if (wifiConnected && !syncInProgress && logCount > 0) {
        if (currentTime - lastSyncAttempt > SYNC_INTERVAL) {
            lastSyncAttempt = currentTime;
            syncOfflineLogs();
        }
    }
    
    delay(50);
}
