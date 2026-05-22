# AWS Cloud Infrastructure Project

A hands-on cloud infrastructure project built entirely on Amazon Web Services, covering the full lifecycle of deploying a production-grade Linux server environment — from bare EC2 provisioning to a working email server with a webmail client, SSL/TLS encryption, and a cloud-hosted file storage platform. Every component is configured manually to demonstrate real-world sysadmin and cloud engineering skills.

> **Domain used throughout this project:** `ops345.lovepreetsingh.site`

---

## Table of Contents

- [Architecture Overview](#architecture-overview)
- [Infrastructure Components](#infrastructure-components)
- [Phase 1 — EC2 Instance Provisioning & User Management](#phase-1--ec2-instance-provisioning--user-management)
- [Phase 2 — Custom VPC Networking & Port Forwarding](#phase-2--custom-vpc-networking--port-forwarding)
- [Phase 3 — Web Server, LVM Storage & Apache/PHP](#phase-3--web-server-lvm-storage--apachephp)
- [Phase 4 — Managed Database (RDS MariaDB) & Nextcloud](#phase-4--managed-database-rds-mariadb--nextcloud)
- [Phase 5 — DNS Configuration with Custom Domain](#phase-5--dns-configuration-with-custom-domain)
- [Phase 6 — SSL/TLS Certificate (Let's Encrypt)](#phase-6--ssltls-certificate-lets-encrypt)
- [Phase 7 — Mail Server (Postfix + Dovecot)](#phase-7--mail-server-postfix--dovecot)
- [Phase 8 — Webmail Client (Roundcube)](#phase-8--webmail-client-roundcube)
- [Config Files](#config-files)
- [Skills Demonstrated](#skills-demonstrated)
- [How to Reproduce This Project](#how-to-reproduce-this-project)

---

## Architecture Overview

<!-- Add architecture diagram image here -->

The project follows a hub-and-spoke network model inside a custom AWS VPC:

- A **Router EC2 instance** with a static Elastic IP acts as the single public-facing entry point. All inbound traffic is forwarded to internal servers via `iptables` NAT rules.
- A **Web/Nextcloud server (www)** sits on a private subnet with no public IP, reachable only through the router.
- An **Email server** with its own Elastic IP handles SMTP (Postfix) and IMAP (Dovecot), and also hosts the Roundcube webmail interface.
- An **RDS MariaDB instance** on a dedicated private subnet serves both Nextcloud and Roundcube.

All internal servers are isolated from the internet and communicate only through security groups and NAT port forwarding — mirroring how production environments are locked down.

---

## Infrastructure Components

| Component | AWS Service / Software | Private IP | Public Access |
|---|---|---|---|
| Router | EC2 (Amazon Linux 2023) | 10.3.45.10 | Elastic IP (router_public_ip) |
| Web + Nextcloud | EC2 (Amazon Linux 2023) | 10.3.45.11 | Via router port forwarding |
| Email + Roundcube | EC2 (Amazon Linux 2023) | 10.3.45.12 | Elastic IP (email_public_ip) |
| Database | RDS MariaDB | AWS-managed endpoint | Private, port 3306 |

**Subnets:**
- `subnet-ops345` — `10.3.45.0/25` — Web & router instances (eu-central-1c)
- `subnet-ops345-db` — `10.3.45.128/25` — RDS subnet group (eu-central-1b)

---

## Phase 1 — EC2 Instance Provisioning & User Management

**Goal:** Launch a baseline EC2 instance and harden it with a non-default user and a static hostname.

Key steps performed:
- Launched an EC2 instance (`t2.micro`, Amazon Linux 2023) with a custom security group allowing only SSH (port 22).
- Created a new Linux user (`lovepreet`), granted passwordless sudo privileges via `/etc/sudoers.d/`, and copied the SSH authorized keys from the default user.
- Deleted the default `ec2-user` to eliminate the known default attack surface.
- Set a fully qualified hostname (`first.lovepreet.ops345.ca`) and made it persistent by setting `preserve_hostname: true` in `/etc/cloud/cloud.cfg`.

<!-- Add screenshot: successful SSH login as the new user with correct hostname prompt -->

---

## Phase 2 — Custom VPC Networking & Port Forwarding

**Goal:** Build a private network from scratch and route all external traffic through a single gateway router using Linux `iptables`.

Key steps performed:
- Created a custom **VPC** (`10.3.45.0/24`) with an **Internet Gateway**, a **Route Table** (default route `0.0.0.0/0` via IGW), and a **Subnet** with auto-assigned public IPs.
- Deployed a dedicated **router EC2 instance** with a static Elastic IP (`router_public_ip`) and a fixed private IP (`10.3.45.10`).
- Enabled **IPv4 forwarding** on the router by setting `net.ipv4.ip_forward = 1` in `/etc/sysctl.conf`.
- Configured `iptables` NAT rules for port forwarding:
  - Port `2211` → `10.3.45.11:22` (SSH to www)
  - Port `2212` → `10.3.45.12:22` (SSH to email)
  - Port `80` → `10.3.45.11:80` (HTTP to web server)
  - Port `443` → `10.3.45.11:443` (HTTPS to web server)
- Saved `iptables` rules persistently with `service iptables save`.

<!-- Add screenshot: successful SSH to internal VM via router port forwarding, showing the internal server's hostname -->

<!-- Add screenshot: iptables -t nat -L -n -v output showing all forwarding rules -->

---

## Phase 3 — Web Server, LVM Storage & Apache/PHP

**Goal:** Set up a production-style web server using LVM-managed persistent storage, Apache, and PHP.

Key steps performed:
- Attached a **2 GB EBS volume** (`www-data`) to the web server instance.
- Used **LVM** to create a volume group (`vg_www`) and a logical volume (`lv_www`), formatted as `ext4`, and mounted persistently at `/var/www` via `/etc/fstab`.
- Installed and configured **Apache (httpd)** and **PHP**.
- Deployed a dynamic `index.php` page that displays the server date/time, private IP, and public IP by querying the AWS EC2 instance metadata service (IMDSv2).
- Verified the web server is accessible from the public internet via the router's Elastic IP on port 80.

The website HTML/PHP source file is stored in the [`config-files/`](./config-files/) folder of this repository.

<!-- Add screenshot: browser showing the live index.php page with instance metadata (private IP, date/time) -->

---

## Phase 4 — Managed Database (RDS MariaDB) & Nextcloud

**Goal:** Deploy a managed relational database and connect it to a self-hosted cloud storage platform (Nextcloud).

Key steps performed:
- Created an **RDS MariaDB instance** (`ops345db`) in a dedicated private subnet (`subnet-ops345-db`), accessible only from the `ops345wwsg` security group on port 3306.
- Disabled SSL-only connections by creating a custom **RDS Parameter Group** (`ops345parametergroup`) with `require_secure_transport = 0`, attached it to the DB instance, and rebooted.
- Installed the `mariadb105` client on the www server and connected to RDS using the endpoint FQDN.
- Created a dedicated database (`firstdb`) and a database user (`lovepreet`) with scoped privileges.
- Downloaded **Nextcloud 31.0.8**, extracted it to `/var/www/html/nextcloud`, and configured Apache with a custom virtual host config (`nextcloud.conf`).
- Installed required PHP 8.3 modules (`php8.3-gd`, `php8.3-zip`, `php8.3-mysqlnd`) and completed the Nextcloud web installer, connecting it to the RDS instance.

The `nextcloud.conf` Apache config file is in the [`config-files/`](./config-files/) folder of this repository.

<!-- Add screenshot: Nextcloud login page or dashboard showing successful setup -->

<!-- Add screenshot: RDS console showing the DB instance in "Available" state -->

---

## Phase 5 — DNS Configuration with Custom Domain

**Goal:** Replace raw IP addresses with a human-readable domain name using a real registered domain.

Domain registrar: **Namecheap**
Domain: `lovepreetsingh.site`

DNS records created:

| Type | Host | Value |
|---|---|---|
| A | `ops345` | Elastic IP of router |
| CNAME | `www.ops345` | `ops345.lovepreetsingh.site` |
| A | `email.ops345` | Elastic IP of email server |
| MX | `ops345` | `email.ops345.lovepreetsingh.site` (priority 10) |

After DNS propagation, the Nextcloud trusted domains config (`/var/www/html/nextcloud/config/config.php`) was updated to include both the subdomain and the `www` variant.

<!-- Add screenshot: browser showing Nextcloud loading at https://ops345.lovepreetsingh.site -->

---

## Phase 6 — SSL/TLS Certificate (Let's Encrypt)

**Goal:** Secure the web server with a free, trusted SSL/TLS certificate using Certbot and DNS challenge verification.

Key steps performed:
- Used **Certbot** on a local Ubuntu WSL machine (`certbot certonly --manual --preferred-challenges dns`) to generate a certificate via DNS-01 challenge.
- Added a `TXT` record (`_acme-challenge.ops345`) to Namecheap DNS and waited for propagation before completing verification.
- Transferred the generated `fullchain.pem` and `privkey.pem` to the www server via SCP through the router port (`2211`).
- Installed `mod_ssl` on the web server and placed certificate files in `/etc/pki/tls/certs/` and `/etc/pki/tls/private/`.
- Updated `/etc/httpd/conf.d/ssl.conf` to point to the new certificate and key.
- Opened port `443` in both the router and www security groups, and added the `443` NAT forwarding rule to `iptables`.

The same certificate generation process was repeated for the email server (`email.ops345.lovepreetsingh.site`) in Phase 8.

<!-- Add screenshot: browser padlock/certificate info showing the valid Let's Encrypt certificate for ops345.lovepreetsingh.site -->

---

## Phase 7 — Mail Server (Postfix + Dovecot)

**Goal:** Deploy a fully functional mail server capable of receiving external email and delivering it to local user mailboxes.

**Infrastructure setup:**
- Launched a new EC2 instance (`email`, private IP `10.3.45.12`) with a 4 GB EBS volume attached and mounted at `/home` via LVM (`vg_email` / `lv_email`).
- Configured router port forwarding: port `2212` → `10.3.45.12:22`.

**Postfix (SMTP):**
- Installed and configured Postfix with the domain (`ops345.lovepreetsingh.site`), set `inet_interfaces = all`, and updated `mydestination`.
- Configured Postfix to deliver mail in **Maildir format** using Dovecot LDA: `mailbox_command = /usr/libexec/dovecot/dovecot-lda`.
- Configured SASL authentication so Postfix delegates login verification to Dovecot.
- Enabled the **submission port (587)** in `master.cf` for authenticated outgoing mail (port 25 is blocked by AWS between clients and servers by design).
- Set up **email aliases** in `/etc/aliases` so that mail sent to `root`, `postmaster`, etc. is delivered to the admin user.

**Dovecot (IMAP):**
- Installed Dovecot and configured `mail_location = maildir:~/Maildir`.
- Enabled IMAP protocol in `dovecot.conf`.
- Configured SSL in `10-ssl.conf` using the Let's Encrypt certificate.
- Configured the `auth` socket in `10-master.conf` so Postfix can use Dovecot for SASL authentication.
- Added `login` to `auth_mechanisms` in `10-auth.conf`.

**Thunderbird desktop client** was used to verify both IMAP (incoming, port 993) and SMTP submission (outgoing, port 587) are working correctly.

The Postfix `main.cf` and `master.cf` config snippets are in the [`config-files/`](./config-files/) folder of this repository.

<!-- Add screenshot: Thunderbird connected to the mail server showing inbox with received emails -->

<!-- Add screenshot: ss -atnp output on email server showing Postfix listening on 25/587 and Dovecot on 143/993 -->

---

## Phase 8 — Webmail Client (Roundcube)

**Goal:** Provide browser-based email access so users don't need a desktop client.

Key steps performed:
- Installed Apache, PHP 8.3, and required PHP modules on the email server.
- Created `/home/www/html` on the persistent EBS volume and symlinked `/var/www → /home/www` so web files survive instance replacement.
- Deployed **Roundcube 1.6.9** to `/var/www/html/roundcube` with Apache ownership.
- Created an HTTPS-only Apache virtual host (`roundcube.conf`) pointing to the Let's Encrypt cert for `email.ops345.lovepreetsingh.site`.
- Created a dedicated `roundcubemail` database in RDS and imported the Roundcube SQL schema.
- Completed the Roundcube web installer and removed the `/installer` directory afterward.
- Configured `config.inc.php` to use `localhost:25` for outgoing SMTP (Postfix on the same server).
- Fixed a display bug where the username showed `lovepreet@localhost` by updating the `mail_host` field in the `users` table to `ops345.lovepreetsingh.site`.

The `roundcube.conf` Apache virtual host file is in the [`config-files/`](./config-files/) folder of this repository.

<!-- Add screenshot: Roundcube webmail interface in browser showing inbox with emails -->

<!-- Add screenshot: Successfully sending an email from Roundcube and receiving it in Thunderbird -->

---

## Config Files

All configuration files referenced in this project are stored in the [`config-files/`](./config-files/) folder:

| File | Description |
|---|---|
| `index.php` | Apache web server homepage with PHP and EC2 metadata |
| `nextcloud.conf` | Apache virtual host config for Nextcloud |
| `ssl.conf` | Apache SSL config for the www server |
| `postfix-main.cf` | Postfix main configuration (SMTP settings, SASL, TLS) |
| `postfix-master.cf` | Postfix master config (enables submission port 587) |
| `dovecot.conf` | Dovecot main config (enables IMAP protocol) |
| `10-mail.conf` | Dovecot mail location config (Maildir format) |
| `10-ssl.conf` | Dovecot SSL config |
| `10-auth.conf` | Dovecot auth mechanisms config |
| `10-master.conf` | Dovecot master config (SASL socket for Postfix) |
| `roundcube.conf` | Apache virtual host config for Roundcube (HTTPS) |
| `roundcube-config.inc.php` | Roundcube application config |

---

## Skills Demonstrated

- **AWS EC2** — instance provisioning, key pairs, security groups, Elastic IPs, EBS volumes
- **AWS VPC** — custom VPC, subnets, Internet Gateway, route tables, multi-AZ design
- **AWS RDS** — managed MariaDB, parameter groups, subnet groups, security group isolation
- **Linux system administration** — user management, sudoers, hostname config, cloud-init
- **Networking** — iptables NAT/port forwarding, IP routing, DNS (A, CNAME, MX, TXT records)
- **LVM** — physical volumes, volume groups, logical volumes, persistent fstab mounts
- **Web server** — Apache httpd, PHP 8.3, virtual hosts, mod_ssl, `.htaccess`
- **SSL/TLS** — Let's Encrypt Certbot, DNS-01 challenge, certificate deployment
- **Mail server** — Postfix (SMTP), Dovecot (IMAP), Maildir format, SASL authentication, email aliases
- **Webmail** — Roundcube deployment and database-backed configuration
- **Security** — least-privilege security groups, no public IPs on internal servers, port 25 awareness, SSL enforcement

---

## How to Reproduce This Project

1. **Clone this repo** and review the [`config-files/`](./config-files/) folder.
2. Create an AWS account and set your region (this project used `eu-central-1`).
3. Follow the phases in order — each phase builds on the last.
4. Replace `lovepreetsingh.site` with your own domain wherever it appears.
5. Replace `ops345` with your own project prefix.
6. All passwords shown in config files are examples — generate strong unique passwords for your own deployment.

> **Cost note:** Running all components simultaneously (2 EC2 instances + 1 RDS instance) will exceed the AWS free tier. Stop or terminate resources when not in use. RDS free tier pauses after 7 days in a stopped state and resumes automatically — remember to re-stop it every 7 days or it will incur charges.