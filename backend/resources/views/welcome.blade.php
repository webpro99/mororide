<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MoroRide | Tourist Ride Marketplace in Morocco</title>
    <meta name="description" content="Book trusted tourist rides across Morocco. Name your price, receive driver-guide offers, and travel safely with MoroRide.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <style>
        :root {
            --navy: #082443;
            --navy-2: #0c3158;
            --ink: #14253f;
            --muted: #56657a;
            --line: #eadfd2;
            --paper: #fffaf4;
            --soft: #f8f4ef;
            --blue-soft: #f1f7ff;
            --terracotta: #b86446;
            --copper: #c78355;
            --gold: #dba45f;
            --green: #43865f;
            --shadow: 0 18px 45px rgba(8, 36, 67, .10);
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            color: var(--ink);
            background: #fff;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 14px;
            line-height: 1.4;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        img {
            display: block;
            max-width: 100%;
        }

        .wrap {
            width: min(1120px, calc(100% - 72px));
            margin: 0 auto;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--navy);
            font-size: 21px;
            font-weight: 800;
        }

        .brand-mark {
            width: 31px;
            height: 31px;
            color: var(--gold);
        }

        .nav {
            position: sticky;
            top: 0;
            z-index: 20;
            background: rgba(255, 250, 244, .94);
            border-bottom: 1px solid rgba(234, 223, 210, .72);
            backdrop-filter: blur(14px);
        }

        .nav-inner {
            position: relative;
            height: 72px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 30px;
        }

        .nav-toggle {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .menu-button {
            width: 42px;
            height: 42px;
            display: none;
            place-items: center;
            border: 1px solid rgba(8, 36, 67, .15);
            border-radius: 7px;
            color: var(--navy);
            background: rgba(255,255,255,.74);
            cursor: pointer;
        }

        .menu-button svg {
            width: 22px;
            height: 22px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 24px;
            flex: 1;
            color: var(--navy);
            font-size: 12px;
            font-weight: 800;
        }

        .nav-links a {
            white-space: nowrap;
        }

        .btn {
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 0 20px;
            border: 1px solid transparent;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
        }

        .btn svg {
            width: 17px;
            height: 17px;
        }

        .btn-primary {
            color: #fff;
            background: var(--navy);
            box-shadow: 0 12px 24px rgba(8, 36, 67, .18);
        }

        .btn-outline {
            color: var(--navy);
            background: rgba(255, 255, 255, .72);
            border-color: #cdd8e3;
        }

        .btn-hotel {
            color: var(--navy);
            background: rgba(255, 249, 243, .86);
            border-color: #d8b790;
        }

        .btn-gold {
            color: #fff;
            background: linear-gradient(135deg, #dca35f, #c17c50);
            box-shadow: 0 14px 28px rgba(191, 124, 80, .25);
        }

        .hero {
            position: relative;
            min-height: 560px;
            overflow: hidden;
            background:
                linear-gradient(90deg, #fffaf4 0%, rgba(255, 250, 244, .96) 40%, rgba(255, 250, 244, .55) 63%, rgba(255, 250, 244, .05) 100%),
                radial-gradient(circle at 42% 32%, rgba(219, 164, 95, .16), transparent 260px),
                #fffaf4;
            border-bottom: 1px solid var(--line);
        }

        .hero::before {
            content: "";
            position: absolute;
            inset: 0 0 0 48%;
            background: url("{{ asset('assets/mororide/hero-marketplace.png') }}") center right / cover no-repeat;
        }

        .hero::after {
            content: "";
            position: absolute;
            inset: auto 0 0;
            height: 130px;
            background: linear-gradient(0deg, #fff 0%, rgba(255,255,255,0));
        }

        .hero-content {
            position: relative;
            z-index: 1;
            width: min(510px, 50%);
            padding: 78px 0 54px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 18px;
            color: var(--navy);
            font-size: 12px;
            font-weight: 800;
        }

        .laurel {
            width: 28px;
            height: 20px;
            color: var(--gold);
        }

        h1,
        h2 {
            margin: 0;
            color: var(--navy);
            font-family: Georgia, "Times New Roman", serif;
            font-weight: 500;
            letter-spacing: 0;
            line-height: .98;
        }

        h1 {
            max-width: 510px;
            font-size: clamp(48px, 6vw, 72px);
        }

        h2 {
            font-size: 29px;
            text-align: center;
        }

        .accent {
            color: var(--terracotta);
        }

        .hero-copy {
            max-width: 445px;
            margin: 20px 0 28px;
            color: #32445f;
            font-size: 17px;
            line-height: 1.38;
        }

        .hero-actions {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 28px;
        }

        .mini-trust {
            display: flex;
            align-items: center;
            gap: 28px;
            flex-wrap: wrap;
            color: var(--navy);
            font-size: 11px;
            font-weight: 800;
        }

        .mini-trust span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .mini-trust svg {
            width: 17px;
            height: 17px;
        }

        section {
            padding: 28px 0;
        }

        .section-kicker {
            margin-bottom: 5px;
            color: #7b8491;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: .08em;
            text-align: center;
            text-transform: uppercase;
        }

        .steps {
            position: relative;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 52px;
            margin: 22px auto 38px;
        }

        .steps::before {
            content: "";
            position: absolute;
            top: 16px;
            left: 14%;
            right: 14%;
            border-top: 1px dashed #9aacbf;
        }

        .step {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 58px 1fr;
            gap: 16px;
            align-items: end;
            padding-top: 48px;
        }

        .step-number {
            position: absolute;
            top: 0;
            left: 50%;
            width: 32px;
            height: 32px;
            display: grid;
            place-items: center;
            transform: translateX(-50%);
            border-radius: 50%;
            color: #fff;
            background: var(--navy);
            font-size: 13px;
            font-weight: 900;
        }

        .step-icon {
            width: 46px;
            height: 46px;
            display: grid;
            place-items: center;
            border-radius: 7px;
            color: #fff;
            background: linear-gradient(135deg, #c97955, #aa5d47);
        }

        .step-icon svg {
            width: 25px;
            height: 25px;
        }

        .step h3,
        .role-card h3,
        .feature h3 {
            margin: 0;
            color: var(--navy);
            font-size: 13px;
            line-height: 1.22;
        }

        .step p,
        .feature p {
            margin: 6px 0 0;
            color: #45566d;
            font-size: 12px;
            line-height: 1.35;
        }

        .role-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }

        .role-card {
            position: relative;
            min-height: 292px;
            overflow: hidden;
            border: 1px solid #e9ded1;
            border-radius: 8px;
            background:
                linear-gradient(90deg, rgba(255,255,255,.96) 0 45%, rgba(255,255,255,.46) 64%, rgba(255,255,255,.08) 100%),
                linear-gradient(135deg, #f3f8fe 0%, #ffffff 70%);
            box-shadow: 0 16px 36px rgba(8, 36, 67, .065);
        }

        .role-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(180deg, rgba(255,255,255,.48), transparent 45%),
                radial-gradient(circle at 78% 48%, rgba(8,36,67,.055), transparent 150px);
            pointer-events: none;
        }

        .role-card.hotel {
            background:
                linear-gradient(90deg, rgba(255,255,255,.96) 0 48%, rgba(255,255,255,.48) 68%, rgba(255,255,255,.08) 100%),
                linear-gradient(135deg, #fff2e7 0%, #ffffff 70%);
        }

        .role-card.driver {
            background:
                linear-gradient(90deg, rgba(255,255,255,.96) 0 46%, rgba(255,255,255,.50) 66%, rgba(255,255,255,.08) 100%),
                linear-gradient(135deg, #f0f7ff 0%, #ffffff 70%);
        }

        .role-copy {
            position: relative;
            z-index: 3;
            width: 50%;
            padding: 28px 18px 24px 24px;
        }

        .role-card.hotel .role-copy {
            width: 54%;
        }

        .role-card.driver .role-copy {
            width: 52%;
        }

        .role-label {
            margin-bottom: 10px;
            color: #bf7a55;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .role-card h3 {
            font-family: Georgia, "Times New Roman", serif;
            max-width: 260px;
            font-size: 24px;
            font-weight: 500;
            line-height: 1.04;
            margin-bottom: 24px;
        }

        .checks {
            display: grid;
            gap: 11px;
            margin: 0;
            padding: 0;
            list-style: none;
            color: #203752;
            font-size: 12px;
            font-weight: 760;
        }

        .checks li {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            line-height: 1.32;
        }

        .checks span {
            width: 15px;
            height: 15px;
            flex: 0 0 15px;
            display: grid;
            place-items: center;
            border: 1px solid #93a8bf;
            border-radius: 50%;
            color: #2467ae;
            font-size: 8px;
            font-weight: 900;
        }

        .role-media {
            position: absolute;
            z-index: 2;
            right: 0;
            bottom: 0;
            width: 55%;
            height: 100%;
            margin: 0;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            pointer-events: none;
            overflow: hidden;
        }

        .role-media img {
            width: 112%;
            height: 108%;
            object-fit: contain;
            object-position: bottom right;
            filter: drop-shadow(0 18px 22px rgba(8, 36, 67, .16));
        }

        .role-card:not(.hotel):not(.driver) .role-media img {
            transform: translate(6px, 12px) scale(1.08);
        }

        .role-card.hotel .role-media {
            width: 58%;
            right: -2%;
            bottom: 0;
        }

        .role-card.hotel .role-media img {
            width: 128%;
            height: 102%;
            object-position: bottom right;
            transform: translate(12px, 16px) scale(1.05);
        }

        .role-card.driver .role-media {
            width: 52%;
            right: 0;
        }

        .role-card.driver .role-media img {
            width: 112%;
            height: 108%;
            transform: translate(3px, 10px) scale(1.08);
        }

        .strip {
            border: 1px solid var(--line);
            border-radius: 8px;
            background: rgba(255,255,255,.94);
            box-shadow: 0 10px 28px rgba(8, 36, 67, .04);
        }

        .safety {
            padding: 20px 22px 22px;
        }

        .safety-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 16px;
            margin-top: 22px;
        }

        .safety-item {
            display: grid;
            justify-items: center;
            gap: 9px;
            color: var(--navy);
            font-size: 11px;
            font-weight: 800;
            line-height: 1.1;
            text-align: center;
        }

        .safety-item svg {
            width: 28px;
            height: 28px;
        }

        .features {
            padding: 22px 34px 24px;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 26px 34px;
            margin-top: 24px;
        }

        .feature {
            display: grid;
            grid-template-columns: 36px 1fr;
            gap: 12px;
        }

        .feature-icon {
            width: 33px;
            height: 33px;
            display: grid;
            place-items: center;
            border-radius: 6px;
            color: var(--copper);
            background: #f5e4d3;
        }

        .feature-icon svg {
            width: 19px;
            height: 19px;
        }

        .cities {
            position: relative;
            overflow: hidden;
            padding: 42px 0;
            background:
                linear-gradient(135deg, #f7fbff 0%, #fffaf4 52%, #ffffff 100%);
        }

        .cities-wrap {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 34px;
            align-items: stretch;
            padding: 30px;
            overflow: hidden;
            border: 1px solid rgba(218, 201, 180, .82);
            border-radius: 10px;
            background:
                radial-gradient(circle at 74% 18%, rgba(219, 164, 95, .18), transparent 180px),
                radial-gradient(circle at 22% 78%, rgba(8, 36, 67, .075), transparent 220px),
                rgba(255,255,255,.84);
            box-shadow: 0 22px 55px rgba(8, 36, 67, .08);
        }

        .cities-copy {
            position: relative;
            z-index: 2;
        }

        .cities .section-kicker,
        .cities h2 {
            text-align: left;
        }

        .cities h2 {
            max-width: 560px;
            font-size: 35px;
        }

        .cities-text {
            max-width: 560px;
            margin: 12px 0 0;
            color: #4b5c72;
            font-size: 14px;
            line-height: 1.55;
        }

        .city-stats {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin: 18px 0 0;
        }

        .city-stat {
            min-width: 118px;
            padding: 12px 14px;
            border: 1px solid rgba(8, 36, 67, .09);
            border-radius: 8px;
            background: rgba(255,255,255,.74);
            box-shadow: 0 10px 24px rgba(8, 36, 67, .05);
        }

        .city-stat strong {
            display: block;
            color: var(--navy);
            font-family: Georgia, "Times New Roman", serif;
            font-size: 24px;
            line-height: 1;
            font-weight: 500;
        }

        .city-stat span {
            display: block;
            margin-top: 4px;
            color: #657286;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .city-list {
            display: grid;
            grid-template-columns: repeat(4, minmax(110px, 1fr));
            gap: 12px;
            max-width: 700px;
            margin-top: 24px;
        }

        .city {
            position: relative;
            min-height: 78px;
            overflow: hidden;
            display: flex;
            align-items: flex-end;
            justify-content: flex-start;
            padding: 9px 10px;
            color: var(--navy);
            font-size: 12px;
            font-weight: 900;
            text-align: left;
            border-radius: 8px;
            background: var(--navy);
            box-shadow: 0 13px 26px rgba(8, 36, 67, .15);
        }

        .city img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: .82;
            transition: transform .25s ease, opacity .25s ease;
        }

        .city::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, transparent 22%, rgba(8, 36, 67, .78));
        }

        .city span {
            position: relative;
            z-index: 1;
            color: #fff;
            text-shadow: 0 1px 8px rgba(0,0,0,.38);
        }

        .city:hover img {
            transform: scale(1.07);
            opacity: .95;
        }

        .map-card {
            position: relative;
            z-index: 2;
            overflow: hidden;
            min-height: 360px;
            display: grid;
            align-content: end;
            border-radius: 10px;
            background:
                radial-gradient(circle at 70% 42%, rgba(219,164,95,.18), transparent 150px),
                linear-gradient(145deg, #fffaf4 0%, #f3e8da 100%);
            border: 1px solid rgba(218, 201, 180, .86);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.72), 0 24px 45px rgba(8, 36, 67, .12);
        }

        .map-card::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(8,36,67,.045) 1px, transparent 1px),
                linear-gradient(0deg, rgba(8,36,67,.035) 1px, transparent 1px);
            background-size: 26px 26px;
            opacity: .72;
        }

        .map-copy {
            position: absolute;
            z-index: 2;
            top: 22px;
            left: 22px;
            right: 22px;
            color: var(--navy);
        }

        .map-copy strong {
            display: block;
            font-family: Georgia, "Times New Roman", serif;
            font-size: 26px;
            line-height: 1.05;
            font-weight: 500;
        }

        .map-copy span {
            display: block;
            margin-top: 8px;
            color: #5b6778;
            font-size: 12px;
            line-height: 1.45;
        }

        .map {
            position: absolute;
            z-index: 1;
            right: 8px;
            bottom: 22px;
            width: 285px;
            max-height: none;
            object-fit: contain;
            object-position: center;
            pointer-events: none;
            opacity: 1;
            filter: drop-shadow(0 20px 26px rgba(8,36,67,.18));
        }

        .route-chip {
            position: absolute;
            z-index: 3;
            left: 22px;
            bottom: 22px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border: 1px solid rgba(8,36,67,.12);
            border-radius: 999px;
            color: var(--navy);
            background: rgba(255,255,255,.72);
            backdrop-filter: blur(10px);
            font-size: 12px;
            font-weight: 800;
            box-shadow: 0 12px 28px rgba(8,36,67,.12);
        }

        .route-chip::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--gold);
            box-shadow: 0 0 0 5px rgba(219,164,95,.2);
        }

        .cta {
            position: relative;
            overflow: hidden;
            min-height: 172px;
            margin-top: 0;
            color: #fff;
            background: linear-gradient(135deg, #082443, #0a2d52);
        }

        .cta-arch {
            position: absolute;
            top: 50%;
            z-index: 1;
            width: auto;
            height: 230px;
            opacity: .94;
            pointer-events: none;
        }

        .cta-arch-left {
            left: 0;
            transform: translateY(-50%) scaleX(-1);
        }

        .cta-arch-right {
            right: 0;
            transform: translateY(-50%);
        }

        .cta-inner {
            position: relative;
            z-index: 2;
            display: grid;
            justify-items: center;
            gap: 13px;
            padding: 18px 0 16px;
            text-align: center;
        }

        .cta h2 {
            max-width: 450px;
            color: #fff;
            font-size: 31px;
            line-height: .98;
        }

        .cta-actions {
            display: flex;
            gap: 28px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .cta .btn-outline {
            color: #fff;
            background: transparent;
            border-color: rgba(255,255,255,.72);
        }

        .cta .btn {
            min-width: 148px;
        }

        .cta-note {
            color: rgba(255,255,255,.82);
            font-size: 11px;
            font-weight: 700;
        }

        .footer {
            color: rgba(255,255,255,.78);
            background: var(--navy);
            border-top: 1px solid rgba(219,164,95,.42);
            padding: 28px 0 18px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.5fr repeat(4, 1fr) 1.1fr;
            gap: 34px;
            align-items: start;
        }

        .footer .brand {
            color: #fff;
            font-size: 20px;
        }

        .footer p {
            max-width: 215px;
            margin: 12px 0 18px;
            font-size: 12px;
            line-height: 1.45;
        }

        .socials {
            display: flex;
            gap: 12px;
            color: rgba(255,255,255,.82);
        }

        .socials span {
            width: 28px;
            height: 28px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            color: rgba(255,255,255,.86);
            border: 1px solid rgba(255,255,255,.22);
            background: rgba(255,255,255,.06);
            font-size: 13px;
            transition: background .2s ease, color .2s ease, transform .2s ease;
        }

        .socials span:hover {
            color: #fff;
            background: rgba(219,164,95,.22);
            transform: translateY(-1px);
        }

        .footer h3 {
            margin: 0 0 12px;
            color: #fff;
            font-size: 11px;
            text-transform: uppercase;
        }

        .footer-links {
            display: grid;
            gap: 8px;
            font-size: 11px;
        }

        .store {
            width: 136px;
            min-height: 42px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 7px 12px;
            border: 1px solid rgba(255,255,255,.38);
            border-radius: 5px;
            color: #fff;
            background: rgba(255,255,255,.035);
            font-size: 11px;
            font-weight: 900;
        }

        .store i {
            width: 21px;
            font-size: 21px;
            text-align: center;
        }

        .store small {
            display: block;
            color: rgba(255,255,255,.68);
            font-size: 8px;
            font-weight: 800;
            line-height: 1;
            text-transform: uppercase;
        }

        .store strong {
            display: block;
            margin-top: 2px;
            color: #fff;
            font-size: 12px;
            line-height: 1;
        }

        .copyright {
            margin-top: 22px;
            padding-top: 14px;
            border-top: 1px solid rgba(255,255,255,.14);
            text-align: center;
            font-size: 11px;
        }

        @media (max-width: 980px) {
            .wrap {
                width: min(100% - 34px, 1120px);
            }

            .menu-button {
                display: grid;
            }

            .nav-links {
                position: absolute;
                top: calc(100% + 10px);
                left: 0;
                right: 0;
                display: none;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
                padding: 12px;
                border: 1px solid rgba(218, 201, 180, .9);
                border-radius: 10px;
                background: rgba(255,250,244,.98);
                box-shadow: 0 22px 50px rgba(8, 36, 67, .16);
                backdrop-filter: blur(16px);
                font-size: 13px;
            }

            .nav-toggle:checked ~ .nav-links {
                display: grid;
            }

            .nav-links a {
                min-height: 42px;
                display: flex;
                align-items: center;
                padding: 0 12px;
                border: 1px solid rgba(8, 36, 67, .08);
                border-radius: 7px;
                background: rgba(255,255,255,.72);
            }

            .hero {
                min-height: auto;
            }

            .hero::before {
                inset: 44% -18% 0 8%;
                opacity: .95;
            }

            .hero-content {
                width: 100%;
                padding-bottom: 360px;
            }

            .steps,
            .role-grid,
            .safety-grid,
            .feature-grid,
            .cities-wrap,
            .footer-grid {
                grid-template-columns: 1fr;
            }

            .steps::before {
                display: none;
            }

            .step {
                grid-template-columns: 54px 1fr;
                padding-top: 0;
            }

            .step-number {
                position: static;
                transform: none;
                grid-column: 1 / -1;
                justify-self: center;
            }

            .role-copy {
                width: 52%;
            }

            .city-list {
                grid-template-columns: repeat(4, 1fr);
            }

            .cities-wrap {
                padding: 22px;
            }

            .map-card {
                min-height: 320px;
            }

            .map {
                right: 16px;
                bottom: 20px;
                width: min(300px, 78vw);
            }
        }

        @media (max-width: 620px) {
            body {
                font-size: 13px;
            }

            section {
                padding: 22px 0;
            }

            .wrap {
                width: min(100% - 24px, 1120px);
            }

            .nav-inner {
                height: 64px;
                gap: 12px;
            }

            .brand {
                font-size: 18px;
            }

            .brand-mark {
                width: 28px;
                height: 28px;
            }

            .nav-inner > .btn {
                display: none;
            }

            .nav-links {
                grid-template-columns: 1fr;
            }

            h1 {
                font-size: 40px;
            }

            h2 {
                font-size: 25px;
            }

            .hero::before {
                inset: 47% -44% 0 -8%;
            }

            .hero-content {
                padding-top: 42px;
                padding-bottom: 310px;
            }

            .hero-copy {
                font-size: 15px;
            }

            .hero-actions,
            .cta-actions {
                display: grid;
                width: 100%;
                gap: 12px;
            }

            .mini-trust {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .role-card {
                min-height: 0;
                display: grid;
                padding-bottom: 0;
            }

            .role-card h3 {
                max-width: none;
                font-size: 25px;
            }

            .role-copy,
            .role-card.hotel .role-copy,
            .role-card.driver .role-copy {
                width: 100%;
                padding: 22px 20px 12px;
            }

            .role-media,
            .role-card.hotel .role-media,
            .role-card.driver .role-media {
                position: relative;
                right: auto;
                bottom: auto;
                width: calc(100% - 32px);
                height: 210px;
                margin: 4px 16px 16px;
                border-radius: 9px;
                background: rgba(255,255,255,.55);
            }

            .role-media img,
            .role-card.hotel .role-media img,
            .role-card.driver .role-media img {
                width: 100%;
                height: 100%;
                transform: none;
                object-position: center bottom;
            }

            .role-card:not(.hotel):not(.driver) .role-media img {
                transform: none;
            }

            .safety-grid,
            .feature-grid,
            .city-list {
                grid-template-columns: repeat(2, 1fr);
            }

            .cities-wrap {
                padding: 18px;
            }

            .cities h2 {
                font-size: 30px;
            }

            .city-stats {
                display: grid;
                grid-template-columns: 1fr;
            }

            .city-list {
                gap: 9px;
            }

            .city {
                min-height: 88px;
            }

            .map-card {
                min-height: 330px;
            }

            .map-copy strong {
                font-size: 22px;
            }

            .map {
                width: min(280px, 82vw);
                right: 8px;
                bottom: 54px;
            }

            .route-chip {
                right: 16px;
                left: 16px;
                justify-content: center;
            }

            .feature {
                grid-template-columns: 34px 1fr;
            }

            .cta h2 {
                font-size: 34px;
            }

            .cta-arch {
                height: 165px;
                opacity: .34;
            }

            .cta-arch-left {
                left: -52px;
            }

            .cta-arch-right {
                right: -52px;
            }

            .footer-grid {
                gap: 22px;
            }
        }
    </style>
</head>
<body>
<nav class="nav">
    <div class="wrap nav-inner">
        <a href="/" class="brand" aria-label="MoroRide home">
            <svg class="brand-mark" viewBox="0 0 48 48" fill="none" aria-hidden="true">
                <path d="M24 3 30 12 41 9 38 20 45 24 38 28 41 39 30 36 24 45 18 36 7 39 10 28 3 24 10 20 7 9 18 12 24 3Z" stroke="currentColor" stroke-width="2"/>
                <path d="M24 13 29 19 35 24 29 29 24 35 19 29 13 24 19 19 24 13Z" stroke="currentColor" stroke-width="2"/>
            </svg>
            MoroRide
        </a>
        <input class="nav-toggle" id="nav-toggle" type="checkbox" aria-label="Toggle navigation">
        <label class="menu-button" for="nav-toggle" aria-label="Open navigation">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
        </label>
        <div class="nav-links">
            <a href="#how">How it Works</a>
            <a href="#tourists">Tourists</a>
            <a href="#hotels">Hotels</a>
            <a href="#drivers">Drivers</a>
            <a href="#safety">Safety</a>
            <a href="#features">Features</a>
            <a href="#cities">Cities</a>
        </div>
        <a class="btn btn-primary" href="/api/fare-config">Book a Ride</a>
    </div>
</nav>

<header class="hero">
    <div class="wrap">
        <div class="hero-content">
            <div class="eyebrow">
                <svg class="laurel" viewBox="0 0 48 30" fill="none" aria-hidden="true"><path d="M17 4C9 7 5 13 5 22M15 9l-5-2M13 14l-6-1M13 20H7" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                #1 Tourist Ride Marketplace in Morocco
                <svg class="laurel" viewBox="0 0 48 30" fill="none" aria-hidden="true"><path d="M31 4c8 3 12 9 12 18M33 9l5-2M35 14l6-1M35 20h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </div>
            <h1>Book trusted tourist rides across <span class="accent">Morocco</span></h1>
            <p class="hero-copy">Set your route, name your price, and receive offers from verified driver-guides. Travel comfortably, safely, and like a local.</p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="/api/fare-config">Book a Ride <svg viewBox="0 0 24 24" fill="none"><path d="m9 5 7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
                <a class="btn btn-outline" href="#drivers"><svg viewBox="0 0 24 24" fill="none"><path d="M20 21a8 8 0 0 0-16 0M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>Join as Driver</a>
                <a class="btn btn-hotel" href="#hotels"><svg viewBox="0 0 24 24" fill="none"><path d="M4 21V5h16v16M8 9h2M14 9h2M10 21v-4h4v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>For Hotels & Riads</a>
            </div>
            <div class="mini-trust">
                <span><svg viewBox="0 0 24 24" fill="none"><path d="M20 21a8 8 0 0 0-16 0M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="2"/></svg>Verified drivers</span>
                <span><svg viewBox="0 0 24 24" fill="none"><path d="M12 21s7-5 7-11a7 7 0 1 0-14 0c0 6 7 11 7 11Z" stroke="currentColor" stroke-width="2"/></svg>Live tracking</span>
                <span><svg viewBox="0 0 24 24" fill="none"><path d="M4 7h16v12H4zM4 10h16M8 15h4" stroke="currentColor" stroke-width="2"/></svg>Secure payments</span>
                <span><svg viewBox="0 0 24 24" fill="none"><path d="M4 21V5h16v16M8 9h2M14 9h2M10 21v-4h4v4" stroke="currentColor" stroke-width="2"/></svg>Hotels & Riads</span>
            </div>
        </div>
    </div>
</header>

<main>
    <section id="how">
        <div class="wrap">
            <div class="section-kicker">How it works</div>
            <h2>Three simple steps to your perfect ride</h2>
            <div class="steps">
                <article class="step">
                    <div class="step-number">1</div>
                    <div class="step-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M12 21s7-5 7-11a7 7 0 1 0-14 0c0 6 7 11 7 11Z" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="10" r="2.5" stroke="currentColor" stroke-width="2"/></svg></div>
                    <div><h3>Set your route</h3><p>Add pickup and drop-off locations and travel details.</p></div>
                </article>
                <article class="step">
                    <div class="step-number">2</div>
                    <div class="step-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M20 12V7l-5-5H6a2 2 0 0 0-2 2v16h16v-8Z" stroke="currentColor" stroke-width="2"/><path d="m14 2 6 6M8 14h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></div>
                    <div><h3>Name your price</h3><p>Propose your fair price in MAD and submit your request.</p></div>
                </article>
                <article class="step">
                    <div class="step-number">3</div>
                    <div class="step-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M20 21a8 8 0 0 0-16 0M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></div>
                    <div><h3>Choose your driver</h3><p>Receive offers, compare, and book with confidence.</p></div>
                </article>
            </div>

            <div class="role-grid">
                <article class="role-card" id="tourists">
                    <div class="role-copy">
                        <div class="role-label">For tourists</div>
                        <h3>Travel Morocco your way</h3>
                        <ul class="checks">
                            <li><span>&check;</span>Name-your-price rides</li>
                            <li><span>&check;</span>Verified local driver-guides</li>
                            <li><span>&check;</span>Live tracking & in-app chat</li>
                            <li><span>&check;</span>Cashless & secure payments</li>
                        </ul>
                    </div>
                    <div class="role-media"><img src="{{ asset('assets/mororide/rider-app.png') }}" alt="Tourist ride tracking app preview"></div>
                </article>
                <article class="role-card hotel" id="hotels">
                    <div class="role-copy">
                        <div class="role-label">For hotels & riads</div>
                        <h3>Delight your guests with seamless transfers</h3>
                        <ul class="checks">
                            <li><span>&check;</span>Concierge dispatch panel</li>
                            <li><span>&check;</span>Manage guest bookings</li>
                            <li><span>&check;</span>Real-time driver tracking</li>
                            <li><span>&check;</span>Branded guest experience</li>
                        </ul>
                    </div>
                    <div class="role-media"><img src="{{ asset('assets/mororide/concierge-dashboard.png') }}" alt="Concierge dashboard preview"></div>
                </article>
                <article class="role-card driver" id="drivers">
                    <div class="role-copy">
                        <div class="role-label">For drivers</div>
                        <h3>More trips. More earnings.</h3>
                        <ul class="checks">
                            <li><span>&check;</span>Receive ride requests</li>
                            <li><span>&check;</span>Accept or counter-offer</li>
                            <li><span>&check;</span>Navigate with live GPS</li>
                            <li><span>&check;</span>Fast payouts to wallet</li>
                        </ul>
                    </div>
                    <div class="role-media"><img src="{{ asset('assets/mororide/driver-requests.png') }}" alt="Driver ride requests app preview"></div>
                </article>
            </div>
        </div>
    </section>

    <section id="safety">
        <div class="wrap strip safety">
            <div class="section-kicker">Safety & trust</div>
            <h2>Your safety is our promise</h2>
            <div class="safety-grid">
                <div class="safety-item"><svg viewBox="0 0 24 24" fill="none"><path d="M7 3h10v18H7zM9 7h6M9 11h6M9 15h3" stroke="currentColor" stroke-width="2"/></svg>Verified<br>Documents</div>
                <div class="safety-item"><svg viewBox="0 0 24 24" fill="none"><path d="M4 8h16v11H4zM8 8l2-3h4l2 3M12 16a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="2"/></svg>Vehicle<br>Photos</div>
                <div class="safety-item"><svg viewBox="0 0 24 24" fill="none"><path d="m12 3 3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1 3-6Z" stroke="currentColor" stroke-width="2"/></svg>Ratings &<br>Reviews</div>
                <div class="safety-item"><svg viewBox="0 0 24 24" fill="none"><path d="M12 21s7-5 7-11a7 7 0 1 0-14 0c0 6 7 11 7 11Z" stroke="currentColor" stroke-width="2"/></svg>Live GPS<br>Tracking</div>
                <div class="safety-item"><svg viewBox="0 0 24 24" fill="none"><path d="M4 5h16v11H8l-4 4V5Z" stroke="currentColor" stroke-width="2"/></svg>In-app<br>Chat</div>
                <div class="safety-item"><svg viewBox="0 0 24 24" fill="none"><path d="M12 2v4M12 18v4M2 12h4M18 12h4M5 5l3 3M16 16l3 3" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2"/></svg>Admin<br>Support</div>
                <div class="safety-item"><svg viewBox="0 0 24 24" fill="none"><path d="M12 7v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" stroke="currentColor" stroke-width="2"/></svg>Ride<br>History</div>
            </div>
        </div>
    </section>

    <section id="features">
        <div class="wrap strip features">
            <div class="section-kicker">Marketplace features</div>
            <h2>Everything you need in one smart platform</h2>
            <div class="feature-grid">
                <div class="feature"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M7 4h10v16H7zM10 8h4M10 12h4" stroke="currentColor" stroke-width="2"/></svg></div><div><h3>Name-Your-Price Rides</h3><p>You set the price that works for you.</p></div></div>
                <div class="feature"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M4 12h4l3-7 3 14 3-7h3" stroke="currentColor" stroke-width="2"/></svg></div><div><h3>Realtime Driver Offers</h3><p>Get multiple offers fast and compare.</p></div></div>
                <div class="feature"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M12 21s7-5 7-11a7 7 0 1 0-14 0c0 6 7 11 7 11Z" stroke="currentColor" stroke-width="2"/></svg></div><div><h3>Live Ride Tracking</h3><p>Track your ride in real-time from pickup to drop-off.</p></div></div>
                <div class="feature"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M4 21V5h16v16M8 9h2M14 9h2M10 21v-4h4v4" stroke="currentColor" stroke-width="2"/></svg></div><div><h3>Concierge Dispatch</h3><p>Create & assign rides for your guests.</p></div></div>
                <div class="feature"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M4 7h16v12H4zM4 10h16M8 15h4" stroke="currentColor" stroke-width="2"/></svg></div><div><h3>Driver Wallet</h3><p>Earnings, payouts and transactions in one place.</p></div></div>
                <div class="feature"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M4 5h16v14H4zM8 9h8M8 13h8" stroke="currentColor" stroke-width="2"/></svg></div><div><h3>Admin Dashboard</h3><p>Powerful tools to manage operations & users.</p></div></div>
                <div class="feature"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M4 5h16v11H8l-4 4V5Z" stroke="currentColor" stroke-width="2"/></svg></div><div><h3>Chat Archive</h3><p>Keep all ride conversations organized and searchable.</p></div></div>
                <div class="feature"><div class="feature-icon"><svg viewBox="0 0 24 24" fill="none"><path d="M12 3v18M5 8h14M7 8l-3 6h6L7 8ZM17 8l-3 6h6l-3-6Z" stroke="currentColor" stroke-width="2"/></svg></div><div><h3>Fare & Commission</h3><p>Transparent pricing and flexible commission rules.</p></div></div>
            </div>
        </div>
    </section>

    <section class="cities" id="cities">
        <div class="wrap cities-wrap">
            <div class="cities-copy">
                <div class="section-kicker">Cities we cover</div>
                <h2>Explore Morocco with local experts</h2>
                <p class="cities-text">From airport pickups to medina transfers and coastal day trips, MoroRide connects tourists with verified local drivers in Morocco's most requested destinations.</p>
                <div class="city-stats">
                    <div class="city-stat"><strong>8</strong><span>live cities</span></div>
                    <div class="city-stat"><strong>24/7</strong><span>ride requests</span></div>
                    <div class="city-stat"><strong>MAD</strong><span>local pricing</span></div>
                </div>
                <div class="city-list">
                    <div class="city"><img src="{{ asset('assets/mororide/casablanca.png') }}" alt="Casablanca"><span>Casablanca</span></div>
                    <div class="city"><img src="{{ asset('assets/mororide/marrakech.png') }}" alt="Marrakech"><span>Marrakech</span></div>
                    <div class="city"><img src="{{ asset('assets/mororide/rabat.png') }}" alt="Rabat"><span>Rabat</span></div>
                    <div class="city"><img src="{{ asset('assets/mororide/fez.png') }}" alt="Fez"><span>Fez</span></div>
                    <div class="city"><img src="{{ asset('assets/mororide/tangier.png') }}" alt="Tangier"><span>Tangier</span></div>
                    <div class="city"><img src="{{ asset('assets/mororide/agadir.png') }}" alt="Agadir"><span>Agadir</span></div>
                    <div class="city"><img src="{{ asset('assets/mororide/essaouira.png') }}" alt="Essaouira"><span>Essaouira</span></div>
                    <div class="city"><img src="{{ asset('assets/mororide/chefchaouen.png') }}" alt="Chefchaouen"><span>Chefchaouen</span></div>
                </div>
            </div>
            <div class="map-card">
                <div class="map-copy">
                    <strong>Tour routes, city hops, and hotel transfers.</strong>
                    <span>Build an itinerary ride network that feels local from the first pickup.</span>
                </div>
                <img class="map" src="{{ asset('assets/mororide/morocco-map.png') }}" alt="Morocco map with MoroRide cities">
                <div class="route-chip">Marrakech -> Essaouira ready</div>
            </div>
        </div>
    </section>

    <section class="cta">
        <img class="cta-arch cta-arch-left" src="{{ asset('assets/mororide/moroccan-arch-side.png') }}" alt="" aria-hidden="true">
        <img class="cta-arch cta-arch-right" src="{{ asset('assets/mororide/moroccan-arch-side.png') }}" alt="" aria-hidden="true">
        <div class="wrap cta-inner">
            <h2>Ready to move tourists better across <span class="accent">Morocco?</span></h2>
            <div class="cta-actions">
                <a class="btn btn-gold" href="/api/fare-config">Book a Ride <svg viewBox="0 0 24 24" fill="none"><path d="m9 5 7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
                <a class="btn btn-outline" href="#hotels">Partner as Hotel</a>
                <a class="btn btn-outline" href="#drivers">Join as Driver</a>
            </div>
            <div class="cta-note">Trusted by travelers, hotels & verified drivers across Morocco</div>
        </div>
    </section>
</main>

<footer class="footer">
    <div class="wrap">
        <div class="footer-grid">
            <div>
                <a href="/" class="brand">
                    <svg class="brand-mark" viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M24 3 30 12 41 9 38 20 45 24 38 28 41 39 30 36 24 45 18 36 7 39 10 28 3 24 10 20 7 9 18 12 24 3Z" stroke="currentColor" stroke-width="2"/><path d="M24 13 29 19 35 24 29 29 24 35 19 29 13 24 19 19 24 13Z" stroke="currentColor" stroke-width="2"/></svg>
                    MoroRide
                </a>
                <p>The cross-platform tourist ride marketplace connecting travelers, hotels, and driver-guides across Morocco.</p>
                <div class="socials">
                    <span aria-label="Facebook"><i class="fa-brands fa-facebook-f" aria-hidden="true"></i></span>
                    <span aria-label="Instagram"><i class="fa-brands fa-instagram" aria-hidden="true"></i></span>
                    <span aria-label="WhatsApp"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></span>
                    <span aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in" aria-hidden="true"></i></span>
                </div>
            </div>
            <div><h3>Company</h3><div class="footer-links"><a href="#how">How It Works</a><a href="#features">Features</a><a href="#cities">Cities</a></div></div>
            <div><h3>Drivers</h3><div class="footer-links"><a href="#drivers">Driver Marketplace</a><a href="#features">Driver Wallet</a><a href="#safety">Driver Safety</a></div></div>
            <div><h3>Hotels & Riads</h3><div class="footer-links"><a href="#hotels">Hotel Dispatch</a><a href="#features">Concierge Tools</a><a href="#safety">Guest Safety</a></div></div>
            <div><h3>Support</h3><div class="footer-links"><a href="#safety">Safety & Trust</a><a href="#features">Platform Features</a><a href="#cities">Covered Cities</a></div></div>
            <div>
                <h3>Download the App</h3>
                <div class="footer-links">
                    <span class="store"><i class="fa-brands fa-apple" aria-hidden="true"></i><span><small>Download on the</small><strong>App Store</strong></span></span>
                    <span class="store"><i class="fa-brands fa-google-play" aria-hidden="true"></i><span><small>Get it on</small><strong>Google Play</strong></span></span>
                </div>
            </div>
        </div>
        <div class="copyright">&copy; 2026 MoroRide. All rights reserved.</div>
    </div>
</footer>
<script>
    document.querySelectorAll('.nav-links a').forEach((link) => {
        link.addEventListener('click', () => {
            const toggle = document.getElementById('nav-toggle');
            if (toggle) {
                toggle.checked = false;
            }
        });
    });
</script>
</body>
</html>
