# Contributing to Property Spotlight

Thank you for your interest in contributing to Property Spotlight.

## How to Contribute

### Reporting Bugs

1. Check existing [issues](https://github.com/Markus-web/Property-Spotlight/issues) to avoid duplicates
2. Create a new issue with:
   - WordPress version
   - PHP version
   - Steps to reproduce
   - Expected vs actual behavior
   - Screenshots if applicable

### Suggesting Features

Open an issue with the `enhancement` label describing:
- The problem you're trying to solve
- Your proposed solution
- Any alternatives you've considered

### Pull Requests

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/your-feature`)
3. Make your changes
4. Test with WordPress 6.4+ and PHP 8.2+
5. Commit with clear messages
6. Push and open a Pull Request

## Development Setup

```bash
# Clone your fork
git clone https://github.com/YOUR-USERNAME/Property-Spotlight.git
cd Property-Spotlight

# Start local WordPress environment
docker compose up -d

# Access at http://localhost:8089
# Login: admin / testpassword123
```

## Code Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- Use meaningful variable and function names
- Add PHPDoc comments for functions and classes
- Escape all output (`esc_html`, `esc_attr`, `esc_url`)
- Sanitize all input (`sanitize_text_field`, `absint`, etc.)
- Use prepared statements for database queries

## Testing

Before submitting a PR:
- Test on WordPress 6.4+ with PHP 8.2+
- Verify no PHP errors or warnings
- Test both shortcode and Gutenberg block
- Check admin panel functionality

## Translations

To add or update translations:
1. Edit `languages/property-spotlight-{locale}.po`
2. Compile to `.mo` using the instructions in README.md
3. Submit PR with both `.po` and `.mo` files

## Questions?

Open an issue or contact [Markus Media](https://markusmedia.fi).
