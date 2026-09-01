# Dynamic Sheet to Post Type Sync & Vehicle Product Grid (v2.1)

A powerful WordPress plugin that automatically fetches and synchronizes **Google Sheet vehicle inventory** into WordPress posts/custom post types and showcases them in a modern, responsive **vehicle showroom catalog**.

---

## 🚀 Key Features

- **🚗 Google Sheet Automated Sync**: Fetches vehicle records from published Google Sheets (CSV) with scheduled WP-Cron or instant 1-click manual sync.
- **🏷️ Multi-Column Title Builder**: Combine multiple Google Sheet columns into clean, single titles (e.g. `{Car Name} {Model} {Year}`).
- **🖼️ Google Drive Multi-Image Slider (AA & AB Columns)**:
  - **Column AA**: Main vehicle image Google Drive link.
  - **Column AB**: Comma-separated sub-image Google Drive links (e.g. `https://drive.google.com/..., https://drive.google.com/...`).
  - Converts all Google Drive links to high-speed streamable direct CDN image URLs.
  - Interactive multi-image slider directly on each card (Next/Prev navigation, dot indicators, image counter `1/N`, and touch swipe support).
- **✨ Streamlined & Clean Card Showcase**:
  - Image Carousel / Slider.
  - Single combined Title (`{Car Name} {Model} {Year}`).
  - Formatted Currency & Price.
  - Creative **"View Details"** button that redirects directly to the dedicated vehicle page.
- **📄 9-Card Real-Time Pagination**:
  - Displays exactly 9 vehicle cards at a time.
  - Instant client-side pagination that works seamlessly with live search and filters.
  - Numbered pagination pills (`« Prev`, `1`, `2`, `3`, `Next »`) with auto-scroll to top.
- **🔍 Creative & Responsive Filter Bar**:
  - Showroom-style header with live search (instant clear button).
  - Clean dropdown filter pills with icons (⛽ Fuel Type, 🗓️ Year, 🕹️ Transmission).
  - Active filter tag chips with 1-click removal.
  - Live result counter (`Showing 1–9 of 24 vehicles`).
- **🚘 Dedicated Full Car Information Page**:
  - Full image gallery with interactive thumbnail selector.
  - Breadcrumbs (`← Back to Inventory / Vehicle Name`).
  - Vehicle ID & Price highlight.
  - Comprehensive specifications grid.
  - Direct WhatsApp inquiry button with pre-filled vehicle details.

---

## 📌 Shortcode Usage

Use the shortcode `[vehicle_products]` or `[vehicle_inventory]` anywhere in WordPress (Gutenberg blocks, Elementor, Classic Editor, or PHP templates):

### 1. Default Showcase (3 Columns, 9 Cards Per Page with Live Filter & Search)
```text
[vehicle_products columns="3" per_page="9" show_filter="yes" show_search="yes"]
```

### 2. 4-Column Grid
```text
[vehicle_products columns="4" per_page="12" show_filter="yes" show_search="yes"]
```

### Available Shortcode Parameters:
| Attribute | Default | Description |
|-----------|---------|-------------|
| `columns` | `3` | Number of columns in desktop grid (`1`, `2`, `3`, or `4`). |
| `per_page` | `9` | Number of vehicle cards to show per page. |
| `post_type` | `post` (or configured PT) | Target post type containing vehicle inventory. |
| `show_filter` | `yes` | Enable/disable dropdown filters (Year, Fuel, Transmission). |
| `show_search` | `yes` | Enable/disable live keyword search bar. |
| `currency` | `$` | Currency symbol for vehicle prices. |
| `orderby` | `date` | Order criteria (`date`, `title`, `meta_value_num`, `rand`). |
| `order` | `DESC` | Sorting direction (`ASC` or `DESC`). |

---

## 🛠️ Google Sheet Setup Guide

1. In your Google Sheet, organize your vehicle columns:
   - **Column B**: `Car ID` (Unique identifier).
   - **Column C**: `Car Name`.
   - **Column D**: `Model`.
   - **Column E**: `Year`.
   - **Column F**: `Price`.
   - **Column AA**: `Main Image URL` (Google Drive share link).
   - **Column AB**: `Sub Images` (Google Drive links separated by `, `).
2. Go to **File > Share > Publish to web**.
3. Under *Link*, select **Entire Document** (or specific sheet) and format as **Comma-separated values (.csv)**.
4. Click **Publish** and copy the generated CSV URL.
5. In WordPress Admin, navigate to **Settings > Vehicle Sheet Sync**, paste the CSV URL, review the mappings, and click **Sync Now**.
