# 🎬 Entertainment Tadka Bot

A powerful Telegram bot for movie requests with admin panel, SQLite database, and multi-channel support.

## 📋 Features

- ✅ Movie request system with daily limits
- ✅ Admin approval/rejection system
- ✅ Auto-notification to users
- ✅ 7 channels support (4 public + 3 private)
- ✅ SQLite database with 7 tables
- ✅ Rate limiting & spam protection
- ✅ Broadcast system for admins
- ✅ Automatic backup system
- ✅ Channel post auto-processing
- ✅ Activity logging
- ✅ Beautiful status page

## 📊 Statistics

- **Total Lines:** ~3,200
- **Total Commands:** 8 (3 public + 5 admin)
- **Database Tables:** 7
- **API Methods:** 30+
- **Features:** 12+

## 🚀 Quick Start

### 1. Deploy on Render.com

1. Fork this repository
2. Connect to Render.com
3. Create new Web Service
4. Set environment variables:
   - `BOT_TOKEN`: Your Telegram bot token
   - `ADMIN_ID`: Your Telegram user ID
5. Deploy!

### 2. Local Development

```bash
# Clone repository
git clone https://github.com/yourusername/entertainment-tadka-bot.git
cd entertainment-tadka-bot

# Copy environment file
cp .env.example .env
# Edit .env with your values

# Run installation
php scripts/install.php

# Set webhook
php scripts/set_webhook.php

# Start bot (polling mode for testing)
php index.php
