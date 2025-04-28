# Contribution Guidelines for MylibSQLAdmin

Thank you for considering contributing to **MylibSQLAdmin**—a modern, open-source GUI for managing libSQL (SQLite-compatible) databases. Your contributions help improve the project for everyone.

## Before You Start

- **Check Existing Issues**: Review [open issues](https://github.com/darkterminal/mylibsqladmin/issues) to avoid duplicating efforts.
- **Understand the Project**: Familiarize yourself with the project's structure and goals by reading the [README](https://github.com/darkterminal/mylibsqladmin/blob/main/README.md) or [DeepWiki](https://deepwiki.com/darkterminal/mylibsqladmin).

## How to Contribute

1. **Fork the Repository**: Click the "Fork" button at the top right of the [repository page](https://github.com/darkterminal/mylibsqladmin).
2. **Clone Your Fork**:
   ```bash
   git clone https://github.com/darkterminal/mylibsqladmin.git
   ```
3. **Create a New Branch**:
   ```bash
   git checkout -b feature/your-feature-name
   ```
4. **Make Your Changes**: Implement your feature or fix.
5. **Test Your Changes**: Ensure your changes don't break existing functionality.
6. **Commit and Push**:
   ```bash
   git add .
   git commit -m "Describe your changes"
   git push origin feature/your-feature-name
   ```
7. **Submit a Pull Request**: Go to your fork on GitHub and click "Compare & pull request."

## Development Environment

- **Docker**: The project uses Docker for development. Refer to the `compose.yml` files for setup.
- **Makefile**: Utilize the provided `Makefile` for common tasks.
- **Environment Variables**: Use `.env.example` as a template for your environment configuration.

## Code Standards

- **Language**: Ensure your code is clean and well-documented.
- **Style**: Follow consistent coding styles and conventions used in the project.
- **Commits**: Write clear and descriptive commit messages.

## 🧾 Licensing

By contributing, you agree that your contributions will be licensed under the [Apache-2.0 License](https://github.com/darkterminal/mylibsqladmin/blob/main/LICENSE).

## 📬 Need Help?

If you have questions or need assistance, feel free to open an issue or [join our community](https://discord.gg/wWDzy5Nt44) discussions.
