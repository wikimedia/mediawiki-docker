#!/usr/bin/env python3
# SPDX-License-Identifier: Apache-2.0

import argparse
import functools
import subprocess
from pathlib import Path

PHP_VERSIONS = {
    "default": "8.1",
}
APCU_VERSION = "5.1.23"
VARIANTS = ["apache", "fpm", "fpm-alpine"]
ROOT_DIR = Path(__file__).parent

APACHE_EXTRA = r"""
# Enable Short URLs
RUN set -eux; \
    a2enmod rewrite; \
    { \
        echo "<Directory /var/www/html>"; \
        echo "  RewriteEngine On"; \
        echo "  RewriteCond %{REQUEST_FILENAME} !-f"; \
        echo "  RewriteCond %{REQUEST_FILENAME} !-d"; \
        echo "  RewriteRule ^ %{DOCUMENT_ROOT}/index.php [L]"; \
        echo "</Directory>"; \
    } > "$APACHE_CONFDIR/conf-available/short-url.conf"; \
    a2enconf short-url

# Enable AllowEncodedSlashes for VisualEditor
RUN sed -i "s/<\/VirtualHost>/\tAllowEncodedSlashes NoDecode\n<\/VirtualHost>/" "$APACHE_CONFDIR/sites-available/000-default.conf"
""".rstrip().replace(
    "    ", "\t"
)


@functools.lru_cache()
def fetch_tags() -> list[str]:
    output = subprocess.check_output(
        [
            "git",
            "ls-remote",
            "--sort=version:refname",
            "--tags",
            "https://github.com/wikimedia/mediawiki.git",
        ],
        text=True,
    )
    tags = []
    for line in output.splitlines():
        ref = line.split("\t", 1)[1]
        if not ref.endswith("^{}"):
            continue
        # strip refs/tags prefix and ^{} suffix
        tags.append(ref[10:][:-3])
    tags.reverse()
    return tags


def latest_version(version: str) -> str:
    for tag in fetch_tags():
        if tag.startswith(f"{version}."):
            return tag
    raise RuntimeError(f"couldn't find release for {version}")


def main():
    parser = argparse.ArgumentParser(description="Update Dockerfiles")
    parser.add_argument(
        "--commit", help="Create Git commit if there are changes", action="store_true"
    )
    parser.add_argument(
        "--pr", help="Open a pull request with the changes", action="store_true"
    )
    args = parser.parse_args()

    updates = set()
    for folder in ROOT_DIR.glob("1.*/"):
        branch = folder.name
        latest = latest_version(branch)
        for variant in VARIANTS:
            vdir = folder / variant
            vdir.mkdir(exist_ok=True)
            base = "alpine" if variant.endswith("-alpine") else "debian"
            template = (ROOT_DIR / f"Dockerfile-{base}.template").read_text()
            new = (
                template.replace(
                    "%%PHP_VERSION%%", PHP_VERSIONS.get(branch, PHP_VERSIONS["default"])
                )
                .replace("%%MEDIAWIKI_MAJOR_VERSION%%", branch)
                .replace("%%MEDIAWIKI_VERSION%%", latest)
                .replace("%%VARIANT%%", variant)
                .replace("%%APCU_VERSION%%", APCU_VERSION)
                .replace(
                    "%%CMD%%",
                    "apache2-foreground" if variant == "apache" else "php-fpm",
                )
                .replace(
                    "%%VARIANT_EXTRAS%%", APACHE_EXTRA if variant == "apache" else ""
                )
            )
            dockerfile = vdir / "Dockerfile"
            if dockerfile.read_text() != new:
                dockerfile.write_text(new)
                print(f"Updated {branch}/{variant}")
                updates.add(latest)
    if not updates:
        print("No changes")
        return
    if not args.commit:
        return

    message = "Update to " + " / ".join(sorted(updates, reverse=True))
    subprocess.check_call(["git", "commit", "-a", "-m", message])

    if not args.pr:
        return
    git_branch = subprocess.check_output(
        ["git", "branch", "--show-current"], text=True
    ).strip()
    subprocess.check_call(["git", "push", "-f", "origin", git_branch])
    subprocess.check_call(["gh", "pr", "create", "--fill"])


if __name__ == "__main__":
    main()
