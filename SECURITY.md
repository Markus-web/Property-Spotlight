# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.2.x   | Yes                |
| 1.1.x   | Security fixes only|
| < 1.1   | No                 |

## Reporting a Vulnerability

If you discover a security vulnerability in Property Spotlight, please report it responsibly.

**Do not open a public GitHub issue for security vulnerabilities.**

### How to Report

1. Email: Send details to the contact form at [markusmedia.fi](https://markusmedia.fi)
2. Include:
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if any)

### What to Expect

- Acknowledgment within 48 hours
- Status update within 7 days
- Fix timeline based on severity:
  - Critical: 24-48 hours
  - High: 7 days
  - Medium: 30 days
  - Low: Next release

### Scope

In scope:
- SQL injection
- Cross-site scripting (XSS)
- Cross-site request forgery (CSRF)
- Authentication/authorization bypass
- Data exposure
- Remote code execution

Out of scope:
- Vulnerabilities in WordPress core
- Vulnerabilities in third-party plugins
- Social engineering attacks
- Denial of service attacks

## Security Best Practices

When using this plugin:
- Keep WordPress and PHP updated
- Use strong API credentials
- Restrict admin access appropriately
- Use HTTPS on your site
