# Dynamic Sheet to Post Type Sync & Vehicle Product Grid (v2.0)

A powerful WordPress plugin that automatically fetches and synchronizes **Google Sheet vehicle inventory** into WordPress posts/custom post types and showcases them in a modern, responsive **WooCommerce-style product card catalog**.

---

## 🚀 Key Features

- **🚗 Google Sheet Automated Sync**: Fetches vehicle records from published Google Sheets (CSV) with scheduled WP-Cron or instant 1-click manual sync.
- **🏷️ Multi-Column Title Builder**: Combine multiple Google Sheet columns into clean, professional titles (e.g. `{Car Name} {Model} {Year}` or `{C} {D} {E}`). No unwanted fallback prefixes (`"Sheet Row Sync"`).
- **🆔 Flexible Car ID & Unique Identifier**: Map **Column B** or `Car ID` as the unique identifier to update existing vehicles and prevent duplicates.
- **✨ Flexible Column Identification**: Supports Google Sheet column letters (`A`, `B`, `C`, `D`, `E`, etc.) or header names (`Car ID`, `Price`, `Fuel`, etc.).
- **🛍️ WooCommerce-Style Product Grid**: Display vehicles in a modern, responsive product grid with:
  - High-res vehicle image with Year / Badge overlay.
  - Formatted currency price tags.
  - Key Specs Pill Grid (🗓️ Year, 🛣️ Mileage, ⛽ Fuel, 🕹️ Transmission).
  - Quick "View Details" popup modal with full specifications table.
  - 💬 Direct **WhatsApp / Inquire** button with pre-filled vehicle details.
- **🔍 Instant Live Search & Filters**: Filter inventory in real-time by keyword, Year, Fuel Type, and Transmission.
- **📋 Customizable Specs & Meta Repeater**: Easily map any extra sheet columns (Engine, Color, Location, Condition) and choose where they display (Card Pill, Image Badge, or Details Modal).

---

## 📌 Shortcode Usage

Use the shortcode `[vehicle_products]` or `[vehicle_inventory]` anywhere in WordPress (Gutenberg blocks, Elementor, Classic Editor, or PHP templates):

### 1. Default Showcase (3 Columns with Live Filter & Search)
```text
[vehicle_products columns="3" posts_per_page="12" show_filter="yes" show_search="yes"]
```

### 2. 4-Column Compact Grid (No Filters)
```text
[vehicle_products columns="4" posts_per_page="8" show_filter="no" show_search="no"]
```

### 3. WhatsApp Direct Inquiry Catalog
```text
[vehicle_products columns="3" whatsapp="15551234567" currency="$"]
```

### Available Shortcode Parameters:
| Attribute | Default | Description |
|-----------|---------|-------------|
| `columns` | `3` | Number of columns in desktop grid (`1`, `2`, `3`, or `4`). |
| `posts_per_page` | `12` | Number of vehicles to display (`-1` for all). |
| `post_type` | `post` (or configured PT) | Target post type containing vehicle inventory. |
| `show_filter` | `yes` | Enable/disable dropdown filters (Year, Fuel, Transmission). |
| `show_search` | `yes` | Enable/disable live keyword search bar. |
| `currency` | `$` | Currency symbol for vehicle prices. |
| `whatsapp` | `""` | Phone number for direct WhatsApp vehicle inquiry with international code. |
| `orderby` | `date` | Order criteria (`date`, `title`, `meta_value_num`, `rand`). |
| `order` | `DESC` | Sorting direction (`ASC` or `DESC`). |

---

## 🛠️ Google Sheet Setup Guide

1. In your Google Sheet, organize your vehicle columns (e.g., Column B: `Car ID`, Column C: `Car Name`, Column D: `Model`, Column E: `Year`, Column F: `Price`, Column G: `Image URL`, Column H: `Mileage`, Column I: `Fuel Type`, etc.).
2. Go to **File > Share > Publish to web**.
3. Under *Link*, select **Entire Document** (or specific sheet) and format as **Comma-separated values (.csv)**.
4. Click **Publish** and copy the generated CSV URL.
5. In WordPress Admin, navigate to **Settings > Vehicle Sheet Sync**, paste the CSV URL, review the mappings, and click **Sync Now**.

---
