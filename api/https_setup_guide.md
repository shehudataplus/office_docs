# HTTPS Configuration and Secure Cookie Settings Guide

## Overview
This guide provides instructions for setting up HTTPS and configuring secure cookies for the Tajnur authentication system.

## 1. HTTPS Configuration

### For Apache Server (.htaccess)
Create or update `.htaccess` in your root directory:

```apache
# Force HTTPS redirect
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Security headers
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
```

### For Nginx
Add to your server block:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com;
    
    # SSL configuration
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    
    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options DENY always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
}
```

## 2. SSL Certificate Setup

### Option 1: Let's Encrypt (Free)
```bash
# Install Certbot
sudo apt-get update
sudo apt-get install certbot python3-certbot-apache

# Generate certificate
sudo certbot --apache -d yourdomain.com

# Auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

### Option 2: Commercial SSL Certificate
1. Purchase SSL certificate from a trusted CA
2. Generate CSR (Certificate Signing Request)
3. Install certificate files on your server
4. Configure web server to use the certificate

## 3. PHP Session Configuration Updates

Update `auth.php` to use secure session settings:

```php
// Add at the beginning of auth.php after session_start()
ini_set('session.cookie_secure', '1');        // HTTPS only
ini_set('session.cookie_httponly', '1');      // No JavaScript access
ini_set('session.cookie_samesite', 'Strict'); // CSRF protection
ini_set('session.use_strict_mode', '1');      // Prevent session fixation

// Set secure session cookie parameters
session_set_cookie_params([
    'lifetime' => 3600,        // 1 hour
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => true,          // HTTPS only
    'httponly' => true,        // No JavaScript access
    'samesite' => 'Strict'     // CSRF protection
]);
```

## 4. Content Security Policy (CSP)

Add CSP header to enhance security:

```php
// Add to auth.php
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com;");
```

## 5. Testing HTTPS Configuration

### Tools for Testing:
1. **SSL Labs SSL Test**: https://www.ssllabs.com/ssltest/
2. **Security Headers**: https://securityheaders.com/
3. **Mozilla Observatory**: https://observatory.mozilla.org/

### Manual Testing:
```bash
# Test HTTPS redirect
curl -I http://yourdomain.com

# Test SSL certificate
openssl s_client -connect yourdomain.com:443 -servername yourdomain.com

# Test security headers
curl -I https://yourdomain.com
```

## 6. Production Checklist

- [ ] SSL certificate installed and valid
- [ ] HTTP to HTTPS redirect working
- [ ] HSTS header configured
- [ ] Secure cookie settings enabled
- [ ] CSP header implemented
- [ ] Security headers configured
- [ ] SSL Labs test shows A+ rating
- [ ] All authentication endpoints use HTTPS
- [ ] Session cookies marked as secure

## 7. Troubleshooting

### Common Issues:
1. **Mixed Content**: Ensure all resources load over HTTPS
2. **Certificate Errors**: Check certificate validity and chain
3. **Cookie Issues**: Verify secure flag is set correctly
4. **CORS Problems**: Update CORS headers for HTTPS

### Debug Commands:
```bash
# Check certificate expiry
openssl x509 -in certificate.crt -text -noout | grep "Not After"

# Test SSL configuration
nmap --script ssl-enum-ciphers -p 443 yourdomain.com
```

## Notes
- This configuration requires server-level access
- Test thoroughly in a staging environment first
- Monitor certificate expiry dates
- Keep security headers updated with best practices
- Consider implementing HTTP/2 for better performance