<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>

## Jannayaks Public Profile Design — Standing Direction

Public Jannayaks profiles should read and feel like refined, responsive digital magazine features, not directory listings or social-media cards.

- Use an editorial page structure with a clear text-led profile heading, the person's name, professional identity or current activity, headline and location.
- Present the biography with refined, readable typography, comfortable line spacing, clear section hierarchy and generous whitespace.
- Use a balanced editorial composition. On desktop, the biography may sit alongside restrained supporting information; on mobile, content should stack naturally and remain easy to read.
- Supporting professional or public-life information and consented public contact details may appear in restrained supporting sections.
- Maintain a dignified, contemporary and consistent Jannayaks visual identity. Do not turn the page into a promotional advertisement.

### Photograph placement — inline only

- Profile photographs must appear only inline within the biography/article flow, at suitable points that support the narrative.
- Do not place a portrait or photograph in the profile hero/header, in a separate gallery, grid, carousel, lightbox gallery strip, sidebar, or other detached photo panel.
- A profile may contain no more than three photographs in total.
- Keep inline photographs responsive, appropriately sized and visually integrated with the surrounding text. On selection, an inline photograph may open enlarged for viewing, but do not turn this into a separate gallery experience.
- Do not invent, generate, or substitute photographs when none have been supplied and approved.

### Non-negotiable product constraints

- One uniform profile presentation for everyone. No tiers, tier names, tier badges, ribbons or tier-based colour variations.
- No video, reels, video placeholders or external video links.
- Public contact details must be shown only where the individual has expressly consented.
- Preserve the approved Jannayaks branding, existing workflow and editorial/customer approval gates.
- The layout must be responsive and accessible. Do not change questionnaire, editorial, payment or publication workflows as part of a presentation-only change.
