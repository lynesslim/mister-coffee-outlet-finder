# Mister Coffee — Outlet Location & Product Stock Finder

A custom WordPress plugin developed for Mister Coffee to showcase all retail hypermarkets, supermarkets, and specialty showrooms across Malaysia and Singapore on an interactive map, complete with live in-store product availability matrices.

## Features
- **Zero Manual Data Entry**: Pre-bundled with all 170 retail outlets and 102 product SKUs extracted directly from the official master Excel workbook.
- **Pinpoint Accurate Google Maps Coordinates**: Coordinates resolved directly from Google Maps URLs.
- **Interactive Multi-Marker Map**: Initially zoomed out to display the entire region (Malaysia & Singapore) on load. Clicking any outlet flies smoothly to its exact street location.
- **Search & Filter Without Forced Selection**: Search by area, outlet, mall, or retailer, or filter by State/Region, In-Store Grinder, and specific coffee product range without disrupting your browsing flow.
- **Detailed Storefront View**: Displays store photo, address, operating hours, phone, in-store product stock checklist with green checkmarks, and one-click "Get Directions" navigation in Google Maps.
- **Customizable Admin Dashboard**:
  - Add new outlets or edit existing ones anytime (address, hours, coordinates, maps link, grinder availability, photo).
  - Add new coffee blends, pods, or machine SKUs and categorize them.
  - Interactive product stock checklist to toggle which products each store carries.
  - Restore / Re-sync button to reset or update the 170 outlets anytime.
- **Clean Widget Design**: Styled with Montserrat semibold headings and Mister Coffee signature `#BC1419` crimson red, with no unnecessary site navigation bars.

## Installation
1. Upload the `mister-coffee-outlet-finder` folder to your WordPress `/wp-content/plugins/` directory (or zip it and upload via **Plugins > Add New > Upload Plugin**).
2. Activate the plugin through the **Plugins** menu in WordPress.
3. The plugin will automatically create its database tables and seed all 170 outlets and 102 products.

## Shortcode Usage
Add the following shortcode to any Page, Post, or Elementor Shortcode widget:
```
[mister_coffee_outlets]
```

### Optional Attributes
- `height`: Custom container height (default: `720px`). Example: `[mister_coffee_outlets height="800px"]`.
