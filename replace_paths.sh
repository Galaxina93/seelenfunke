#!/bin/bash

# Target Files: All blade templates, PHP controllers, JS/CSS files
TARGETS="resources/views app/Livewire public/css public/js vite.config.js tailwind.config.js"

echo "Running Asset Path Switcher on $TARGETS..."

find $TARGETS -type f \( -name "*.php" -o -name "*.js" -o -name "*.css" \) -exec sed -i \
  -e "s|asset('funkira/|asset('shop/ai/funkira/|g" \
  -e "s|asset(\"funkira/|asset(\"shop/ai/funkira/|g" \
  -e "s|/funkira/|/shop/ai/funkira/|g" \
  -e "s|'funkira/|'shop/ai/funkira/|g" \
  -e "s|\"funkira/|\"shop/ai/funkira/|g" \
  \
  -e "s|asset('gamification/|asset('shop/customer/gamification/|g" \
  -e "s|asset(\"gamification/|asset(\"shop/customer/gamification/|g" \
  -e "s|/gamification/|/shop/customer/gamification/|g" \
  -e "s|'gamification/|'shop/customer/gamification/|g" \
  -e "s|\"gamification/|\"shop/customer/gamification/|g" \
  \
  -e "s|asset('todo/|asset('shop/management/todo/|g" \
  -e "s|asset(\"todo/|asset(\"shop/management/todo/|g" \
  -e "s|/todo/|/shop/management/todo/|g" \
  \
  -e "s|asset('images/configurator/|asset('shop/product/configurator/|g" \
  -e "s|asset(\"images/configurator/|asset(\"shop/product/configurator/|g" \
  -e "s|/images/configurator/|/shop/product/configurator/|g" \
  -e "s|'images/configurator/|'shop/product/configurator/|g" \
  -e "s|\"images/configurator/|\"shop/product/configurator/|g" \
  \
  -e "s|asset('images/cookie/|asset('shop/system/cookie/|g" \
  -e "s|asset(\"images/cookie/|asset(\"shop/system/cookie/|g" \
  -e "s|/images/cookie/|/shop/system/cookie/|g" \
  -e "s|'images/cookie/|'shop/system/cookie/|g" \
  -e "s|\"images/cookie/|\"shop/system/cookie/|g" \
  \
  -e "s|asset('images/projekt/|asset('shop/master/projekt/|g" \
  -e "s|asset(\"images/projekt/|asset(\"shop/master/projekt/|g" \
  -e "s|/images/projekt/|/shop/master/projekt/|g" \
  -e "s|'images/projekt/|'shop/master/projekt/|g" \
  -e "s|\"images/projekt/|\"shop/master/projekt/|g" \
  \
  -e "s|asset('images/profile.webp')|asset('shop/customer/profile.webp')|g" \
  -e "s|asset(\"images/profile.webp\")|asset(\"shop/customer/profile.webp\")|g" \
  -e "s|/images/profile.webp|/shop/customer/profile.webp|g" \
  -e "s|'images/profile.webp'|'shop/customer/profile.webp'|g" \
  -e "s|\"images/profile.webp\"|\"shop/customer/profile.webp\"|g" \
  {} +

echo "Done!"
