# Sai Ganapathi Home Needs – Showroom Management & Biometric Kiosk System

A comprehensive retail inventory management and automated attendance web application tailored for **Sai Ganapathi Home Needs**, Narsipatnam. The platform integrates a public showroom catalog, counter billing management, administrative inventory operations, and an in-browser AI biometric kiosk with GPS geofencing.

---

## 🌟 Key Features

* **Public Product Catalog & Live Stock:**
  * Interactive UI built with Tailwind CSS and animated SVG brand elements.
  * Real-time search by appliance name, brand, or model number.
  * Automatic stock status badges (`In Stock`, `Low Stock`, `Out of Stock`) and discount calculations.

* **Biometric Attendance Kiosk (`counter/attendance.php`):**
  * In-browser facial recognition powered by `@vladmandic/face-api` (TinyFaceDetector, 68-point landmarks, 128-dimensional facial descriptors).
  * Live on-screen Euclidean distance scoring and real-time bounding box feedback.
  * Haversine GPS geofence verification to restrict punches to showroom coordinates.
  * Rapid duplicate punch prevention (cooldown timer).

* **POS Billing & Showroom Counter:**
  * Counter sales interface with printable receipts (`print_bill.php`) and transactional sales history tracking.

* **Admin Operations Suite:**
  * Role-authenticated dashboard for stock intake, expense tracking, and vendor management.
  * Staff enrollment module that extracts and stores facial feature vectors directly into MySQL.

---

## 🛠️ Tech Stack

* **Backend:** PHP (PDO)
* **Frontend:** HTML5, Tailwind CSS, JavaScript (ES6+), Face-API.js
* **Database:** MySQL
* **Deployment/Server:** Apache / XAMPP

---

## 🚀 Setup & Installation

1. **Clone the Repository:**
   ```bash
   git clone [https://github.com/devendrasabbavarapu31-afk/HOME_NEEDS_APPLICATION.git](https://github.com/devendrasabbavarapu31-afk/HOME_NEEDS_APPLICATION.git)