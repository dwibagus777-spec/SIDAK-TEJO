---
name: kilo-ssh-triage
description: Use when Kilo Code reports HTTP 499 or upstream-provider errors, VS Code Remote-SSH hangs or fails, SSH port forwarding is refused, or a Kilo task cannot reach the intended remote workspace.
---

# Kilo and Remote-SSH Triage

Use this skill to identify which connection layer failed and gather the smallest useful diagnostic. It cannot repair a Kilo provider outage, change Hostinger SSH policy, or grant access that is not configured.

## 1. Classify the failure

- **Kilo AI request / HTTP 499:** If the response says `upstream provider was unable to process the request`, treat it as a Kilo gateway/provider request failure. Record model/provider, approximate time, whether one retry or another configured provider changes the result, and the redacted error text. Do not diagnose this as SSH.
- **SSH authentication or transport:** SSH itself cannot authenticate, resolve the host, or connect to its configured port. Diagnose the SSH config, key agent/passphrase prompt, host availability, and exact OpenSSH error.
- **VS Code Remote-SSH setup/forwarding:** OpenSSH may connect, but VS Code Server startup or a forwarded port fails (for example `channel open failed`, `Connection refused`, or `Failed to set up dynamic port forwarding`). Check the `Remote - SSH` Output channel and determine whether the remote server process is still alive before changing anything.
- **Wrong workspace:** Confirm the remote indicator/host and terminal `hostname` plus `pwd` before running project commands. A local terminal is not the production server.

## 2. Gather evidence safely

1. Ask for the exact error text and the failing stage; use existing logs before retrying commands.
2. Separate a plain SSH connection check from VS Code server/forwarding checks. Do not repeatedly reconnect in a loop.
3. Redact usernames if needed, host/IP when sensitive, tokens, cookies, `.env`, private-key contents, and passphrases. Never request or print secrets.
4. Prefer read-only inspection. Do not delete VS Code server directories, reset Kilo databases, edit `sshd_config`, alter provider/model settings, or change production files as a diagnostic shortcut.
5. For `administratively prohibited`, explain that server-side TCP forwarding policy may be blocking the tunnel; only the server administrator can authorize a policy change. For `Connection refused`, identify which host/port refused and inspect service/log evidence before suggesting a restart.

## 3. Report the next action

State: **layer**, **evidence**, **likely owner** (Kilo/provider, local SSH client, VS Code remote server, or remote SSH administrator), and **one next check**. Do not claim a skill fixed the external service. Do not deploy or run migrations as part of connection troubleshooting.

## Official references

- Kilo Skills: https://github.com/Kilo-Org/kilocode/blob/main/packages/kilo-docs/pages/customize/skills.md
- VS Code Remote-SSH troubleshooting: https://code.visualstudio.com/docs/remote/troubleshooting
