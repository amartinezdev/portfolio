<?php
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

session_start();

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

function env(string $name, $default = null)
{
  $value = getenv($name);
  if ($value !== false) {
    return $value;
  }
  if (array_key_exists($name, $_ENV) && $_ENV[$name] !== null) {
    return $_ENV[$name];
  }
  if (array_key_exists($name, $_SERVER) && $_SERVER[$name] !== null) {
    return $_SERVER[$name];
  }
  return $default;
}

function recordSubmissionAttempt(): void
{
  if (!isset($_SESSION['contact_submission_timestamps']) || !is_array($_SESSION['contact_submission_timestamps'])) {
    $_SESSION['contact_submission_timestamps'] = [];
  }

  $timestamps = $_SESSION['contact_submission_timestamps'];
  $timestamps[] = time();
  $cutoff = time() - 600;
  $_SESSION['contact_submission_timestamps'] = array_values(array_filter($timestamps, static fn($timestamp) => $timestamp >= $cutoff));
}

function isRateLimited(int $maxAttempts = 3, int $windowSeconds = 600): bool
{
  if (!isset($_SESSION['contact_submission_timestamps']) || !is_array($_SESSION['contact_submission_timestamps'])) {
    return false;
  }

  $cutoff = time() - $windowSeconds;
  $recent = array_filter($_SESSION['contact_submission_timestamps'], static fn($timestamp) => $timestamp >= $cutoff);
  $_SESSION['contact_submission_timestamps'] = array_values($recent);

  return count($recent) >= $maxAttempts;
}

$mensaje_enviado = false;
$error_mensaje = "";

if (isset($_SESSION['mensaje_enviado'])) {
  $mensaje_enviado = $_SESSION['mensaje_enviado'];
  unset($_SESSION['mensaje_enviado']);
}
if (isset($_SESSION['error_mensaje'])) {
  $error_mensaje = $_SESSION['error_mensaje'];
  unset($_SESSION['error_mensaje']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Honeypot anti-spam: campo oculto que solo rellenan los bots
  if (!empty($_POST['website'])) {
    header('Location: /#contact');
    exit;
  }

  recordSubmissionAttempt();

  // Recibir los datos del formulario
  $nombre = htmlspecialchars($_POST['nombre']);
  $apellidos = htmlspecialchars($_POST['apellidos']);
  $email = htmlspecialchars($_POST['email']);
  $asunto = htmlspecialchars($_POST['asunto']);
  $mensaje = htmlspecialchars($_POST['mensaje']);

  // Validar que los campos requeridos no estén vacíos
  if (empty($nombre) || empty($apellidos) || empty($email) || empty($asunto) || empty($mensaje)) {
    $error_mensaje = "Todos los campos son obligatorios.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error_mensaje = "El email no es válido.";
  } elseif (isRateLimited()) {
    $error_mensaje = "Has enviado demasiados mensajes en poco tiempo. Intenta de nuevo en 10 minutos.";
  } else {
    $smtpHost = env('SMTP_HOST', 'smtp.gmail.com');
    $smtpUser = env('SMTP_USERNAME');
    $smtpPass = env('SMTP_PASSWORD');
    $smtpPort = env('SMTP_PORT', 587);
    $smtpSecureStr = env('SMTP_SECURE', 'tls');
    switch ($smtpSecureStr) {
      case 'ssl':
        $smtpSecure = PHPMailer::ENCRYPTION_SMTPS;
        break;
      case 'tls':
      default:
        $smtpSecure = PHPMailer::ENCRYPTION_STARTTLS;
        break;
    }
    $mailTo = env('MAIL_TO', 'alvaromartinezdev@gmail.com');

    if (!$smtpUser || !$smtpPass) {
      $error_mensaje = "Configuración SMTP incompleta. Revisa tu archivo .env.";
    } else {
      $mail = new PHPMailer(true);

      try {
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = $smtpSecure;
        $mail->Port = $smtpPort;

        // Destinatarios
        $mail->setFrom($smtpUser, 'Contacto Web');
        $mail->addReplyTo($email, $nombre . ' ' . $apellidos);
        $mail->addAddress($mailTo, 'Álvaro Martínez');

        // Contenido
        $mail->isHTML(true);
        $mail->Subject = 'Nuevo mensaje desde el formulario de contacto: ' . $asunto;
        $mail->Body = "
        <html>
        <head>
            <title>Nuevo mensaje de contacto</title>
        </head>
        <body>
            <h2>Nuevo mensaje desde el sitio web</h2>
            <p><strong>Nombre:</strong> $nombre $apellidos</p>
            <p><strong>Email:</strong> $email</p>
            <p><strong>Asunto:</strong> $asunto</p>
            <p><strong>Mensaje:</strong></p>
            <p>$mensaje</p>
        </body>
        </html>
        ";
        $mail->AltBody = "Nombre: $nombre $apellidos\nEmail: $email\nAsunto: $asunto\nMensaje: $mensaje";

        $mail->send();
        $_SESSION['mensaje_enviado'] = true;
        header('Location: /#contact');
        exit;
      } catch (Exception $e) {
        $error_mensaje = "Error al enviar el mensaje: " . $e->getMessage();
        header('Location: /#contact');
      }
    }
  }
}
?>
<!doctype html>
<html lang="es" data-bs-theme="dark">

<head>
  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-5GK9N4DJD2"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-5GK9N4DJD2');
  </script>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- Google Tag Manager -->
  <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
  new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
  j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
  'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
  })(window,document,'script','dataLayer','GTM-TGNZRFLL');</script>
  <!-- End Google Tag Manager -->
  <title>Álvaro Martínez | Desarrollador Web Full Stack en Murcia</title>
  <meta name="description" content="Álvaro Martínez, desarrollador web Full Stack en Murcia. Webs y aplicaciones a medida como freelance para empresas y particulares. Proyectos y contacto." />
  <meta name="author" content="Álvaro Martínez" />
  <meta name="robots" content="index, follow" />
  <link rel="canonical" href="https://alvaromartinez.dev/" />

  <!-- Open Graph -->
  <meta property="og:type" content="profile" />
  <meta property="profile:first_name" content="Álvaro" />
  <meta property="profile:last_name" content="Martínez" />
  <meta property="og:site_name" content="Álvaro Martínez | Portfolio" />
  <meta property="og:url" content="https://alvaromartinez.dev/" />
  <meta property="og:title" content="Álvaro Martínez | Desarrollador Web Full Stack en Murcia" />
  <meta property="og:description" content="Desarrollador web Full Stack Junior en Murcia. Java, Spring Boot, React, Node.js, PHP, MySQL. Proyectos, certificados y contacto." />
  <meta property="og:image" content="https://alvaromartinez.dev/img/brand/og-terminal.png" />
  <meta property="og:image:type" content="image/png" />
  <meta property="og:image:width" content="1200" />
  <meta property="og:image:height" content="630" />
  <meta property="og:image:alt" content="Terminal del portfolio de Álvaro Martínez, desarrollador web Full Stack en Murcia" />
  <meta property="og:locale" content="es_ES" />

  <!-- Twitter Cards -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="Álvaro Martínez | Desarrollador Web Full Stack en Murcia" />
  <meta name="twitter:description" content="Desarrollador web Full Stack Junior en Murcia. Java, Spring Boot, React, Node.js, PHP, MySQL." />
  <meta name="twitter:image" content="https://alvaromartinez.dev/img/brand/og-terminal.png" />
  <meta name="twitter:image:alt" content="Terminal del portfolio de Álvaro Martínez, desarrollador web Full Stack en Murcia" />

  <meta name="theme-color" content="#0d0d0d" media="(prefers-color-scheme: dark)" />
  <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)" />
  <link rel="manifest" href="manifest.webmanifest" />

  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin />
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
    crossorigin="anonymous" />
  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
    crossorigin="anonymous"
    defer></script>
  <link rel="icon" href="img/brand/am_bw.png" type="image/png" />
  <link rel="apple-touch-icon" href="img/brand/am_bw.png" />
  <!-- Poppins y JetBrains Mono se sirven en local (assets/fonts/, @font-face en styles.scss); ya no hace falta Google Fonts. -->
  <link rel="stylesheet" href="assets/css/style.css" />
  <link rel="stylesheet" href="assets/css/styles.css" />

  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "Person",
        "@id": "https://alvaromartinez.dev/#person",
        "name": "Álvaro Martínez",
        "alternateName": "alvaromartinezdev",
        "url": "https://alvaromartinez.dev/",
        "image": {
          "@type": "ImageObject",
          "@id": "https://alvaromartinez.dev/#personimage",
          "url": "https://alvaromartinez.dev/img/profile/alvaro-martinez.webp",
          "caption": "Álvaro Martínez, desarrollador web Full Stack en Murcia"
        },
        "description": "Desarrollador web Full Stack Junior en Murcia, especializado en Java, Spring Boot, JavaScript, React y Node.js. Portfolio con proyectos y experiencia.",
        "jobTitle": "Desarrollador Web Full Stack",
        "hasOccupation": {
          "@type": "Occupation",
          "name": "Desarrollador Web Full Stack",
          "occupationLocation": {
            "@type": "City",
            "name": "Murcia, España"
          }
        },
        "homeLocation": {
          "@type": "Place",
          "name": "Murcia, España"
        },
        "knowsAbout": [
          "Desarrollo web",
          "Desarrollo Full Stack",
          "Java",
          "Spring Boot",
          "JavaScript",
          "React",
          "Angular",
          "Node.js",
          "Express",
          "PHP",
          "Laravel",
          "REST APIs",
          "MySQL",
          "PostgreSQL",
          "SQLite",
          "Git",
          "Docker",
          "HTML",
          "CSS",
          "Responsive Web Design",
          "Desarrollo web freelance",
          "Diseño web a medida",
          "Mantenimiento web",
          "SEO técnico"
        ],
        "knowsLanguage": "es",
        "hasOfferCatalog": {
          "@type": "OfferCatalog",
          "name": "Servicios de desarrollo web freelance",
          "itemListElement": [
            {
              "@type": "Offer",
              "itemOffered": {
                "@type": "Service",
                "name": "Diseño y desarrollo web a medida",
                "description": "Diseño y desarrollo de webs corporativas a medida, sin CMS ni plantillas: adaptadas a móvil, con tema claro y oscuro y SEO técnico desde el primer día.",
                "serviceType": "Diseño y desarrollo web",
                "provider": { "@id": "https://alvaromartinez.dev/#person" },
                "areaServed": [
                  { "@type": "City", "name": "Murcia" },
                  { "@type": "Country", "name": "España" }
                ],
                "availableChannel": {
                  "@type": "ServiceChannel",
                  "serviceUrl": "https://alvaromartinez.dev/#contact"
                }
              }
            },
            {
              "@type": "Offer",
              "itemOffered": {
                "@type": "Service",
                "name": "Aplicaciones web a medida",
                "description": "Aplicaciones y paneles de gestión a medida con usuarios, roles y permisos, base de datos propia, histórico auditable y API REST documentada.",
                "serviceType": "Desarrollo de software a medida",
                "provider": { "@id": "https://alvaromartinez.dev/#person" },
                "areaServed": [
                  { "@type": "City", "name": "Murcia" },
                  { "@type": "Country", "name": "España" }
                ],
                "availableChannel": {
                  "@type": "ServiceChannel",
                  "serviceUrl": "https://alvaromartinez.dev/#contact"
                }
              }
            },
            {
              "@type": "Offer",
              "itemOffered": {
                "@type": "Service",
                "name": "Mantenimiento y optimización web",
                "description": "Auditoría de rendimiento, SEO y accesibilidad, corrección de errores, actualizaciones y copias de seguridad sobre webs ya existentes.",
                "serviceType": "Mantenimiento web",
                "provider": { "@id": "https://alvaromartinez.dev/#person" },
                "areaServed": [
                  { "@type": "City", "name": "Murcia" },
                  { "@type": "Country", "name": "España" }
                ],
                "availableChannel": {
                  "@type": "ServiceChannel",
                  "serviceUrl": "https://alvaromartinez.dev/#contact"
                }
              }
            }
          ]
        },
        "email": "mailto:alvaromartinezdev@gmail.com",
        "sameAs": [
          "https://github.com/amartinezdev",
          "https://www.linkedin.com/in/alvaromartinezdev"
        ],
        "hasCredential": [
          {
            "@type": "EducationalOccupationalCredential",
            "credentialCategory": "certificate",
            "name": "IA Generativa y su impacto en el negocio"
          },
          {
            "@type": "EducationalOccupationalCredential",
            "credentialCategory": "certificate",
            "name": "JavaScript · Udemy"
          },
          {
            "@type": "EducationalOccupationalCredential",
            "credentialCategory": "certificate",
            "name": "Full-Stack Engineer"
          },
          {
            "@type": "EducationalOccupationalCredential",
            "credentialCategory": "certificate",
            "name": "Data Engineer"
          },
          {
            "@type": "EducationalOccupationalCredential",
            "credentialCategory": "certificate",
            "name": "Claude Code in Action"
          }
        ],
        "mainEntityOfPage": {
          "@id": "https://alvaromartinez.dev/#webpage"
        }
      },
      {
        "@type": "WebSite",
        "@id": "https://alvaromartinez.dev/#website",
        "name": "Álvaro Martínez | Portfolio",
        "url": "https://alvaromartinez.dev/",
        "inLanguage": "es",
        "publisher": {
          "@id": "https://alvaromartinez.dev/#person"
        }
      },
      {
        "@type": "ProfilePage",
        "@id": "https://alvaromartinez.dev/#webpage",
        "url": "https://alvaromartinez.dev/",
        "name": "Álvaro Martínez | Desarrollador Web Full Stack en Murcia",
        "description": "Álvaro Martínez, desarrollador web Full Stack Junior en Murcia. Especializado en Java, Spring Boot, JavaScript, React, Node.js, PHP y MySQL.",
        "isPartOf": {
          "@id": "https://alvaromartinez.dev/#website"
        },
        "about": {
          "@id": "https://alvaromartinez.dev/#person"
        },
        "mainEntity": {
          "@id": "https://alvaromartinez.dev/#person"
        },
        "primaryImageOfPage": {
          "@id": "https://alvaromartinez.dev/#personimage"
        },
        "dateModified": "2026-08-22",
        "inLanguage": "es"
      }
    ]
  }
  </script>

  <script>
    (function () {
      var guardado = localStorage.getItem("theme");
      var prefiereClaro = window.matchMedia("(prefers-color-scheme: light)").matches;
      var tema = guardado || (prefiereClaro ? "light" : "dark");
      document.documentElement.setAttribute("data-bs-theme", tema);
    })();
  </script>
  <!-- Sin JS, nada dispara .is-visible (ni el IntersectionObserver de
       scrollReveal.js ni el timeout de heroReveal.js): esto evita que
       el contenido con .reveal (incluidos el nombre, el rol y el botón
       del hero) se quede permanentemente en opacity:0. -->
  <noscript>
    <style>
      .reveal {
        opacity: 1 !important;
        transform: none !important;
      }
    </style>
  </noscript>
</head>

<body class="vh-100">
  <div id="scrollProgress" class="scroll-progress" aria-hidden="true"></div>
  <!-- Google Tag Manager (noscript) -->
  <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TGNZRFLL"
  height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
  <!-- End Google Tag Manager (noscript) -->
  <svg xmlns="http://www.w3.org/2000/svg" style="display: none">
    <symbol id="icon-github" viewBox="0 0 16 16">
      <path
        d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27s1.36.09 2 .27c1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0 0 16 8c0-4.42-3.58-8-8-8" />
    </symbol>
    <symbol id="icon-linkedin" viewBox="0 0 16 16">
      <path
        d="M0 1.146C0 .513.526 0 1.175 0h13.65C15.474 0 16 .513 16 1.146v13.708c0 .633-.526 1.146-1.175 1.146H1.175C.526 16 0 15.487 0 14.854zm4.943 12.248V6.169H2.542v7.225zm-1.2-8.212c.837 0 1.358-.554 1.358-1.248-.015-.709-.52-1.248-1.342-1.248S2.4 3.226 2.4 3.934c0 .694.521 1.248 1.327 1.248zm4.908 8.212V9.359c0-.216.016-.432.08-.586.173-.431.568-.878 1.232-.878.869 0 1.216.662 1.216 1.634v3.865h2.401V9.25c0-2.22-1.184-3.252-2.764-3.252-1.274 0-1.845.7-2.165 1.193v.025h-.016l.016-.025V6.169h-2.4c.03.678 0 7.225 0 7.225z" />
    </symbol>
    <symbol id="icon-envelope" viewBox="0 0 16 16">
      <path
        d="M2 2A2 2 0 0 0 .05 3.555L8 8.414l7.95-4.859A2 2 0 0 0 14 2zm-2 9.8V4.698l5.803 3.546zm6.761-2.97-6.57 4.026A2 2 0 0 0 2 14h6.256A4.5 4.5 0 0 1 8 12.5a4.49 4.49 0 0 1 1.606-3.446l-.367-.225L8 9.586zM16 9.671V4.697l-5.803 3.546.338.208A4.5 4.5 0 0 1 12.5 8c1.414 0 2.675.652 3.5 1.671" />
      <path
        d="M15.834 12.244c0 1.168-.577 2.025-1.587 2.025-.503 0-1.002-.228-1.12-.648h-.043c-.118.416-.543.643-1.015.643-.77 0-1.259-.542-1.259-1.434v-.529c0-.844.481-1.4 1.26-1.4.585 0 .87.333.953.63h.03v-.568h.905v2.19c0 .272.18.42.411.42.315 0 .639-.415.639-1.39v-.118c0-1.277-.95-2.326-2.484-2.326h-.04c-1.582 0-2.64 1.067-2.64 2.724v.157c0 1.867 1.237 2.654 2.57 2.654h.045c.507 0 .935-.07 1.18-.18v.731c-.219.1-.643.175-1.237.175h-.044C10.438 16 9 14.82 9 12.646v-.214C9 10.36 10.421 9 12.485 9h.035c2.12 0 3.314 1.43 3.314 3.034zm-4.04.21v.227c0 .586.227.8.581.8.31 0 .564-.17.564-.743v-.367c0-.516-.275-.708-.572-.708-.346 0-.573.245-.573.791" />
    </symbol>
    <symbol id="icon-browser-chrome" viewBox="0 0 16 16">
      <path
        fill-rule="evenodd"
        d="M16 8a8 8 0 0 1-7.022 7.94l1.902-7.098a3 3 0 0 0 .05-1.492A3 3 0 0 0 10.237 6h5.511A8 8 0 0 1 16 8M0 8a8 8 0 0 0 7.927 8l1.426-5.321a3 3 0 0 1-.723.255 3 3 0 0 1-1.743-.147 3 3 0 0 1-1.043-.7L.633 4.876A8 8 0 0 0 0 8m5.004-.167L1.108 3.936A8.003 8.003 0 0 1 15.418 5H8.066a3 3 0 0 0-1.252.243 2.99 2.99 0 0 0-1.81 2.59M8 10a2 2 0 1 0 0-4 2 2 0 0 0 0 4" />
    </symbol>
    <symbol id="icon-download" viewBox="0 0 16 16">
      <path
        d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5" />
      <path
        d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708z" />
    </symbol>
    <symbol id="icon-arrow-up" viewBox="0 0 16 16">
      <path
        fill-rule="evenodd"
        d="M8 15a.5.5 0 0 0 .5-.5V2.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L7.5 2.707V14.5a.5.5 0 0 0 .5.5" />
    </symbol>
  </svg>
  <nav class="navbar navbar-expand-lg bg-dark navbar-dark fixed-top">
    <a class="navbar-brand ms-4 mt-0 position-absolute" href="#inicio">
      <img src="img/brand/am_wb.png" alt="Álvaro Martínez logo" width="50" height="50" fetchpriority="high" />
    </a>
    <button
      class="navbar-toggler d-lg-none ms-auto me-3"
      type="button"
      data-bs-toggle="collapse"
      data-bs-target="#collapse"
      aria-controls="collapse"
      aria-expanded="false"
      aria-label="activar navegacion">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse mt-2" id="collapse">
      <ul class="navbar-nav mt-2 mt-lg-0 mx-auto gap-0 gap-lg-3 text-center">
        <li class="nav-item">
          <a class="nav-link" href="#inicio">inicio</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#about">sobre_mi</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#projects">proyectos</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#certificados">certificados</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#skills">skills</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#servicios">servicios</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="#contact">contacto</a>
        </li>
      </ul>
      <button class="btn position-absolute top-50 end-0 translate-middle-y me-4" id="cambiarTema" onclick="changeTheme()" aria-pressed="false" aria-label="Alternar tema" title="Alternar tema" type="button">
        <svg class="theme-icon moon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
        </svg>
        <svg class="theme-icon sun" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
          <path d="M8 4.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z"/>
          <path d="M8 0a.5.5 0 0 1 .5.5V2a.5.5 0 0 1-1 0V.5A.5.5 0 0 1 8 0zm0 14a.5.5 0 0 1 .5.5V16a.5.5 0 0 1-1 0v-1.5A.5.5 0 0 1 8 14zM2.343 2.343a.5.5 0 0 1 .707 0L4.5 3.793a.5.5 0 1 1-.707.707L2.343 3.05a.5.5 0 0 1 0-.707zm9.95 9.95a.5.5 0 0 1 .707 0l1.45 1.45a.5.5 0 0 1-.707.707l-1.45-1.45a.5.5 0 0 1 0-.707zM0 8a.5.5 0 0 1 .5-.5H2a.5.5 0 0 1 0 1H.5A.5.5 0 0 1 0 8zm14 0a.5.5 0 0 1 .5-.5H16a.5.5 0 0 1 0 1h-1.5A.5.5 0 0 1 14 8zM2.343 13.657a.5.5 0 0 1 0-.707l1.45-1.45a.5.5 0 1 1 .707.707l-1.45 1.45a.5.5 0 0 1-.707 0zm9.95-9.95a.5.5 0 0 1 0-.707l1.45-1.45a.5.5 0 1 1 .707.707l-1.45 1.45a.5.5 0 0 1-.707 0z"/>
        </svg>
      </button>
    </div>
    <div></div>
  </nav>

  <!--  HEADER  -->
  <header class="hero-section" id="inicio">
    <div class="win hero-window reveal" data-reveal-step="1">
      <div class="win-bar">
        <span class="win-dot"></span><span class="win-dot"></span><span class="win-dot"></span>
        <span class="win-name">zsh — ~/alvaromartinez.dev</span>
      </div>
      <div class="hero-body">
        <p class="term-prompt mb-0 reveal" data-reveal-step="2">~ $ echo $SALUDO</p>
        <p class="hero-greeting reveal" data-reveal-step="2">👋 <span id="palabras">Hey!</span></p>
        <p class="term-prompt mb-0 reveal" data-reveal-step="3">~ $ whoami</p>
        <h1 class="hero-name reveal" data-reveal-step="3">Álvaro Martínez</h1>
        <p class="term-prompt mb-0 reveal" data-reveal-step="4">~ $ echo $PROFESION</p>
        <h2 class="hero-role reveal" data-reveal-step="4">Desarrollador Web Full Stack en Murcia</h2>
        <a class="btn boton reveal" data-reveal-step="5" href="#about" role="button">→ sobre_mi.sh</a>
      </div>
    </div>
  </header>

  <!-- <div class="col-12 d-none d-lg-block" id="about"></div> -->

  <!-- <div class="w-100 altura d-block" ></div> -->

  <main>
  <div class="container" id="about">
    <!--  ABOUT  -->
    <section class="section-py">
      <div class="section-eyebrow reveal">
        <span class="num">01</span>
        <h2 class="section-title mb-0">sobre_mi/</h2>
      </div>
      <div class="row g-4 align-items-center mt-2 about-card reveal">
        <div class="col-12 mb-4 mb-md-0 col-md-auto">
          <div class="win about-photo-window">
            <div class="win-bar">
              <span class="win-dot"></span><span class="win-dot"></span><span class="win-dot"></span>
              <span class="win-name">alvaro.webp</span>
            </div>
            <img src="img/profile/alvaro-martinez.webp" alt="Álvaro Martínez, desarrollador web Full Stack en Murcia" width="280" height="330" />
          </div>
        </div>
        <div class="col-12 text-start col-md">
          <p class="lead">
Desarrollador web Full Stack Junior de Murcia, graduado en DAW. Trabajo con <span class="tag">Java</span>&#8288;, <span class="tag">Spring Boot</span>&#8288;, <span class="tag">JavaScript</span>&#8288;, <span class="tag">React</span>&#8288;, <span class="tag">Node.js</span> y <span class="tag">SQL</span>&#8288;, desarrollando aplicaciones web completas desde la base de datos hasta la interfaz de usuario.
          </p>
          <div class="d-flex flex-wrap justify-content-center justify-content-md-start align-items-center gap-3 mt-4">
            <nav class="nav align-items-center gap-2">
              <a class="nav-link icono" href="https://github.com/amartinezdev/" target="_blank" rel="noopener noreferrer" title="GitHub" aria-label="GitHub">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" class="bi bi-github" fill="currentColor" aria-hidden="true">
                  <use href="#icon-github"></use>
                </svg>
              </a>
              <a class="nav-link icono" href="https://www.linkedin.com/in/alvaromartinezdev" target="_blank" rel="noopener noreferrer" title="LinkedIn" aria-label="LinkedIn">
                <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" class="bi bi-linkedin" fill="currentColor" aria-hidden="true">
                  <use href="#icon-linkedin"></use>
                </svg>
              </a>
              <a class="nav-link icono" href="mailto:alvaromartinezdev@gmail.com" title="Correo electrónico" aria-label="Correo electrónico">
                <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" class="bi bi-envelope-at-fill" fill="currentColor" aria-hidden="true">
                  <use href="#icon-envelope"></use>
                </svg>
              </a>
            </nav>
            <a class="btn boton" href="docs/ALVARO_MARTINEZ_CV.pdf" download="ALVARO_MARTINEZ_CV.pdf">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-download" aria-hidden="true">
                <use href="#icon-download"></use>
              </svg>
              descargar_cv.pdf
            </a>
          </div>
        </div>
      </div>

      <div class="timeline">
        <p class="term-prompt mb-0 reveal">~ $ cat experiencia.log</p>
        <div class="timeline-track">
          <span class="timeline-line" aria-hidden="true"></span>
          <span class="timeline-line-fill" aria-hidden="true"></span>
          <div class="timeline-item reveal">
            <span class="timeline-dot" aria-hidden="true"></span>
            <div class="timeline-content">
              <span class="timeline-date mono">02/2026 – 06/2026</span>
              <h3 class="timeline-role">Desarrollador de Software <span class="timeline-company">@ NTT DATA</span></h3>
              <div class="timeline-meta">
                <span class="tag">Murcia, España</span>
                <span class="tag">Contrato de prácticas</span>
              </div>
              <ul class="timeline-list">
                <li>Desarrollé backend con <span class="tag">Python</span> y <span class="tag">SQL</span> para aplicaciones corporativas.</li>
                <li>Diseñé y mantuve consultas sobre bases de datos relacionales.</li>
                <li>Desarrollé nuevas funcionalidades y resolví incidencias siguiendo metodología ágil <span class="tag">SCRUM</span>&#8288;.</li>
              </ul>
            </div>
          </div>

          <div class="timeline-item reveal">
            <span class="timeline-dot" aria-hidden="true"></span>
            <div class="timeline-content">
              <span class="timeline-date mono">03/2023 – 06/2024</span>
              <h3 class="timeline-role">Técnico Informático <span class="timeline-company">@ 4GL</span></h3>
              <div class="timeline-meta">
                <span class="tag">España</span>
              </div>
              <ul class="timeline-list">
                <li>Automaticé e integré software corporativo, mejorando los procesos internos.</li>
                <li>Aumenté la productividad interna en un <span class="tag">20%</span> mediante la optimización de procesos.</li>
                <li>Diagnostiqué y resolví incidencias técnicas, documentando cada solución.</li>
              </ul>
            </div>
          </div>

          <div class="timeline-item reveal">
            <span class="timeline-dot" aria-hidden="true"></span>
            <div class="timeline-content">
              <span class="timeline-date mono">02/2019 – 03/2023</span>
              <h3 class="timeline-role">Empleado de Back Office <span class="timeline-company">@ ISGF</span></h3>
              <div class="timeline-meta">
                <span class="tag">España</span>
              </div>
              <ul class="timeline-list">
                <li>Ascendido a Back Office en menos de 8 meses por cumplimiento de objetivos.</li>
                <li>Coordiné un equipo como Líder de Equipo, con seguimiento de tareas mediante <span class="tag">KPIs</span>&#8288;.</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <!--  PROYECTOS  -->
  <div class="container" id="projects">
    <section class="section-py">
      <div class="section-eyebrow reveal">
        <span class="num">02</span>
        <h2 class="section-title mb-0">proyectos/</h2>
      </div>
      <p class="section-lede mb-4 reveal">Una muestra de proyectos prácticos, de la base de datos a la interfaz.</p>

      <div class="project-list">
        <div class="project-item reveal">
          <img
            src="img/projects/stock3d.webp"
            srcset="img/projects/stock3d-350.webp 350w, img/projects/stock3d.webp 700w"
            sizes="(min-width: 768px) 260px, (min-width: 480px) 420px, 90vw"
            class="project-image"
            alt="Captura del panel de Stock3D, aplicación de gestión de inventario de filamento 3D desarrollada con Spring Boot y Angular"
            width="700"
            height="525"
            loading="lazy" />
          <div class="project-body">
            <h3 class="project-title">&gt; Stock3D</h3>
            <div class="project-tags">
              <span class="tag">Spring Boot</span>
              <span class="tag">Angular</span>
              <span class="tag">PostgreSQL</span>
            </div>
            <p class="project-desc">Aplicación full stack hecha <strong>para un cliente real</strong> que imprime en 3D: controla los rollos cerrados, el material en uso, las ventas y un histórico auditable de cada movimiento. API REST con autenticación JWT y roles, <strong>52 tests</strong> en el backend y despliegue en contenedores con Docker.</p>
            <div class="project-links">
              <a href="https://github.com/amartinezdev/stock3d-app" target="_blank" rel="noopener noreferrer" class="cmd-link">$ github</a>
            </div>
          </div>
        </div>

        <div class="project-item reveal">
          <img
            src="img/projects/mondejar-turpin.webp"
            srcset="img/projects/mondejar-turpin-350.webp 350w, img/projects/mondejar-turpin.webp 700w"
            sizes="(min-width: 768px) 260px, (min-width: 480px) 420px, 90vw"
            class="project-image"
            alt="Captura de la web del despacho de abogados Mondéjar y Turpín, desarrollada a medida con HTML, CSS, JavaScript y PHP"
            width="700"
            height="525"
            loading="lazy" />
          <div class="project-body">
            <h3 class="project-title">&gt; Mondéjar &amp; Turpín</h3>
            <div class="project-tags">
              <span class="tag">JavaScript</span>
              <span class="tag">PHP</span>
              <span class="tag">SEO</span>
            </div>
            <p class="project-desc">Web corporativa a medida para un despacho de abogados de Murcia: <strong>proyecto real para un cliente real, hoy en producción</strong>. Desarrollada <strong>sin CMS ni plantillas</strong>, con HTML, CSS y JavaScript propios, formulario de contacto en PHP, tema claro/oscuro y SEO técnico con datos estructurados.</p>
            <div class="project-links">
              <a href="https://mondejaryturpin.com" target="_blank" rel="noopener noreferrer" class="cmd-link cmd-link--preview-glow">$ preview</a>
            </div>
          </div>
        </div>

        <div class="project-item reveal">
          <img
            src="img/projects/cine.webp"
            srcset="img/projects/cine-350.webp 350w, img/projects/cine.webp 700w"
            sizes="(min-width: 768px) 260px, (min-width: 480px) 420px, 90vw"
            class="project-image"
            alt="Captura del proyecto Cine, una aplicación de gestión de cartelera desarrollada con Laravel"
            width="700"
            height="525"
            loading="lazy" />
          <div class="project-body">
            <h3 class="project-title">&gt; Cine</h3>
            <div class="project-tags">
              <span class="tag">Laravel</span>
              <span class="tag">MySQL</span>
            </div>
            <p class="project-desc">Aplicación de gestión de cartelera para un cine construida con Laravel: catálogo público de películas, panel de administración y notificaciones por Telegram.</p>
            <div class="project-links">
              <a href="https://github.com/amartinezdev/cine" target="_blank" rel="noopener noreferrer" class="cmd-link">$ github</a>
              <a href="https://alvaromartinez.dev/cine/login" target="_blank" rel="noopener noreferrer" class="cmd-link cmd-link--preview-glow">$ preview</a>
            </div>
          </div>
        </div>

        <div class="project-item reveal">
          <img
            src="img/projects/restaurante.webp"
            srcset="img/projects/restaurante-350.webp 350w, img/projects/restaurante.webp 700w"
            sizes="(min-width: 768px) 260px, (min-width: 480px) 420px, 90vw"
            class="project-image"
            alt="Captura del proyecto Restaurante, desarrollado con PHP y MySQL"
            width="700"
            height="525"
            loading="lazy" />
          <div class="project-body">
            <h3 class="project-title">&gt; Restaurante</h3>
            <div class="project-tags">
              <span class="tag">PHP</span>
              <span class="tag">MySQL</span>
            </div>
            <p class="project-desc">Proyecto práctico de un restaurante desarrollado con PHP y MySQL. Simula la funcionalidad de un restaurante real.</p>
            <div class="project-links">
              <a href="https://github.com/amartinezdev/restaurante" target="_blank" rel="noopener noreferrer" class="cmd-link">$ github</a>
              <a href="https://alvaromartinez.dev/restaurante" target="_blank" rel="noopener noreferrer" class="cmd-link cmd-link--preview-glow">$ preview</a>
            </div>
          </div>
        </div>

        <div class="project-item reveal">
          <img
            src="img/projects/pong.webp"
            srcset="img/projects/pong-350.webp 350w, img/projects/pong.webp 700w"
            sizes="(min-width: 768px) 260px, (min-width: 480px) 420px, 90vw"
            class="project-image"
            alt="Captura del juego Pong recreado con Phaser y Vite"
            width="700"
            height="525"
            loading="lazy" />
          <div class="project-body">
            <h3 class="project-title">&gt; Ping Pong</h3>
            <div class="project-tags">
              <span class="tag">Phaser</span>
              <span class="tag">Vite</span>
            </div>
            <p class="project-desc">Recreación del clásico Pong, desarrollado con Phaser e integrado con Vite. Permite partidas locales para dos jugadores.</p>
            <div class="project-links">
              <a href="https://github.com/amartinezdev/pong" target="_blank" rel="noopener noreferrer" class="cmd-link">$ github</a>
              <a href="https://alvaromartinez.dev/pong" target="_blank" rel="noopener noreferrer" class="cmd-link cmd-link--preview-glow">$ preview</a>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <!-- CERTIFICADOS -->
  <div class="container">
    <section class="section-py" id="certificados">
      <div class="section-eyebrow reveal">
        <span class="num">03</span>
        <h2 class="section-title mb-0">certificados/</h2>
      </div>
      <p class="section-lede mb-4 reveal">Formación complementaria en IA, ingeniería de datos y desarrollo full-stack.</p>

      <div class="win certs-window reveal">
        <div class="win-bar">
          <span class="win-dot"></span><span class="win-dot"></span><span class="win-dot"></span>
          <span class="win-name">certificados.log</span>
        </div>
        <div class="certs-grid">
          <button type="button" class="cert-tile" data-bs-toggle="modal" data-bs-target="#certModal" data-cert-full="img/certs/AI-gen.png" data-cert-full-alt="Certificado de IA Generativa y su impacto en el negocio" aria-label="Ver certificado ampliado: IA Generativa y su impacto en el negocio">
            <img src="img/certs/AI-gen-sm.png" srcset="img/certs/AI-gen-sm-260.png 260w, img/certs/AI-gen-sm.png 440w" sizes="220px" alt="Certificado de IA Generativa y su impacto en el negocio" width="440" height="339" loading="lazy" draggable="false" />
            <span class="cert-tile-caption">IA Generativa</span>
          </button>
          <button type="button" class="cert-tile" data-bs-toggle="modal" data-bs-target="#certModal" data-cert-full="img/certs/udemy_JS.jpg" data-cert-full-alt="Certificado de JavaScript en Udemy" aria-label="Ver certificado ampliado: JavaScript · Udemy">
            <img src="img/certs/udemy_JS_sm.png" srcset="img/certs/udemy_JS_sm-260.png 260w, img/certs/udemy_JS_sm.png 440w" sizes="220px" alt="Certificado de JavaScript en Udemy" width="440" height="339" loading="lazy" draggable="false" />
            <span class="cert-tile-caption">JavaScript &middot; Udemy</span>
          </button>
          <button type="button" class="cert-tile" data-bs-toggle="modal" data-bs-target="#certModal" data-cert-full="img/certs/full-stack-eng.png" data-cert-full-alt="Certificado de Full-Stack Engineer" aria-label="Ver certificado ampliado: Full-Stack Engineer">
            <img src="img/certs/full-stack-sm.png" srcset="img/certs/full-stack-sm-260.png 260w, img/certs/full-stack-sm.png 440w" sizes="220px" alt="Certificado de Full-Stack Engineer" width="440" height="339" loading="lazy" draggable="false" />
            <span class="cert-tile-caption">Full-Stack Engineer</span>
          </button>
          <button type="button" class="cert-tile" data-bs-toggle="modal" data-bs-target="#certModal" data-cert-full="img/certs/data-eng.png" data-cert-full-alt="Certificado de Data Engineer" aria-label="Ver certificado ampliado: Data Engineer">
            <img src="img/certs/data-eng-sm.png" srcset="img/certs/data-eng-sm-260.png 260w, img/certs/data-eng-sm.png 440w" sizes="220px" alt="Certificado de Data Engineer" width="440" height="339" loading="lazy" draggable="false" />
            <span class="cert-tile-caption">Data Engineer</span>
          </button>
          <button type="button" class="cert-tile" data-bs-toggle="modal" data-bs-target="#certModal" data-cert-full="img/certs/claude.png" data-cert-full-alt="Certificado de Claude Code in Action" aria-label="Ver certificado ampliado: Claude Code in Action">
            <img src="img/certs/claude-sm.png" srcset="img/certs/claude-sm-260.png 260w, img/certs/claude-sm.png 440w" sizes="220px" alt="Certificado de Claude Code in Action" width="440" height="339" loading="lazy" draggable="false" />
            <span class="cert-tile-caption">Claude Code in Action</span>
          </button>
        </div>
      </div>
    </section>

    <!-- SKILLS -->
    <section class="section-py" id="skills">
      <div class="section-eyebrow reveal">
        <span class="num">04</span>
        <h2 class="section-title mb-0">skills/</h2>
      </div>
      <p class="section-lede mb-4 reveal">Tecnologías y herramientas con las que trabajo a diario.</p>

      <div class="skills-grid-outer">
        <div class="win reveal">
          <div class="win-bar">
            <span class="win-dot"></span><span class="win-dot"></span><span class="win-dot"></span>
            <span class="win-name">frontend/</span>
          </div>
          <div class="skills-grid">
            <a href="https://developer.mozilla.org/es/docs/Web/HTML" class="skill-tile" target="_blank" rel="noopener noreferrer">
              <img src="img/icons/HTML.svg" alt="HTML" width="28" height="28" loading="lazy" /><span>HTML</span>
            </a>
            <a href="https://www.w3schools.com/css/" class="skill-tile" target="_blank" rel="noopener noreferrer">
              <img src="img/icons/CSS.svg" alt="CSS" width="28" height="28" loading="lazy" /><span>CSS</span>
            </a>
            <a href="https://www.w3schools.com/js/" class="skill-tile" target="_blank" rel="noopener noreferrer">
              <img src="img/icons/JavaScript.svg" alt="JavaScript" width="28" height="28" loading="lazy" /><span>JavaScript</span>
            </a>
            <a href="https://es.react.dev/" class="skill-tile" target="_blank" rel="noopener noreferrer">
              <img src="img/icons/React-Dark.svg" alt="React" width="28" height="28" loading="lazy" /><span>React</span>
            </a>
            <a href="https://angular.dev/" class="skill-tile" target="_blank" rel="noopener noreferrer">
              <img src="img/icons/Angular-Dark.svg" alt="Angular" width="28" height="28" loading="lazy" /><span>Angular</span>
            </a>
            <a href="https://tailwindcss.com/" class="skill-tile" target="_blank" rel="noopener noreferrer">
              <img src="img/icons/TailwindCSS-Dark.svg" alt="Tailwind CSS" width="28" height="28" loading="lazy" /><span>Tailwind</span>
            </a>
          </div>
        </div>

        <div class="win reveal">
          <div class="win-bar">
            <span class="win-dot"></span><span class="win-dot"></span><span class="win-dot"></span>
            <span class="win-name">backend/</span>
          </div>
          <div class="skills-grid">
            <a href="https://nodejs.org/" class="skill-tile" target="_blank" rel="noopener noreferrer">
              <img src="img/icons/NodeJS-Dark.svg" alt="Node.js" width="28" height="28" loading="lazy" /><span>Node.js</span>
            </a>
            <a href="https://expressjs.com/es/" class="skill-tile" target="_blank" rel="noopener noreferrer">
              <img src="img/icons/ExpressJS-Dark.svg" alt="ExpressJS" width="28" height="28" loading="lazy" /><span>Express</span>
            </a>
            <a href="https://www.java.com/es/" class="skill-tile" target="_blank" rel="noopener noreferrer">
              <img src="img/icons/Java-Dark.svg" alt="Java" width="28" height="28" loading="lazy" /><span>Java</span>
            </a>
            <a href="https://spring.io/projects/spring-boot/" class="skill-tile" target="_blank" rel="noopener noreferrer">
              <img src="img/icons/Spring-Dark.svg" alt="Spring" width="28" height="28" loading="lazy" /><span>Spring</span>
            </a>
            <a href="https://www.php.net/" class="skill-tile" target="_blank" rel="noopener noreferrer">
              <img src="img/icons/PHP-Dark.svg" alt="PHP" width="28" height="28" loading="lazy" /><span>PHP</span>
            </a>
            <a href="https://laravel.com/" class="skill-tile" target="_blank" rel="noopener noreferrer">
              <img src="img/icons/Laravel-Dark.svg" alt="Laravel" width="28" height="28" loading="lazy" /><span>Laravel</span>
            </a>
          </div>
        </div>

        <div class="win reveal">
          <div class="win-bar">
            <span class="win-dot"></span><span class="win-dot"></span><span class="win-dot"></span>
            <span class="win-name">bbdd/</span>
          </div>
          <div class="skills-grid">
            <a href="https://mysql.com/" class="skill-tile" target="_blank" rel="noopener noreferrer">
              <img src="img/icons/MySQL-Dark.svg" alt="MySQL" width="28" height="28" loading="lazy" /><span>MySQL</span>
            </a>
            <a href="https://www.postgresql.org/" class="skill-tile" target="_blank" rel="noopener noreferrer">
              <img src="img/icons/PostgreSQL-Dark.svg" alt="PostgreSQL" width="28" height="28" loading="lazy" /><span>PostgreSQL</span>
            </a>
            <a href="https://www.sqlite.org/" class="skill-tile" target="_blank" rel="noopener noreferrer">
              <img src="img/icons/SQLite.svg" alt="SQLite" width="28" height="28" loading="lazy" /><span>SQLite</span>
            </a>
          </div>
        </div>

        <div class="win reveal">
          <div class="win-bar">
            <span class="win-dot"></span><span class="win-dot"></span><span class="win-dot"></span>
            <span class="win-name">herramientas/</span>
          </div>
          <div class="skills-grid">
            <a href="https://git-scm.com/" class="skill-tile" target="_blank" rel="noopener noreferrer">
              <img src="img/icons/Git.svg" alt="Git" width="28" height="28" loading="lazy" /><span>Git</span>
            </a>
            <a href="https://github.com/amartinezdev/" class="skill-tile" target="_blank" rel="noopener noreferrer">
              <img src="img/icons/Github-Dark.svg" alt="GitHub" width="28" height="28" loading="lazy" /><span>GitHub</span>
            </a>
            <a href="https://www.docker.com/" class="skill-tile" target="_blank" rel="noopener noreferrer">
              <img src="img/icons/Docker.svg" alt="Docker" width="28" height="28" loading="lazy" /><span>Docker</span>
            </a>
          </div>
        </div>
      </div>
    </section>
  </div>

  <!-- Modal de certificados: uno solo, reutilizado para todos. El src/alt
       se rellena por JS (ver assets/js/certModal.js) leyendo los
       data-cert-full/-alt del botón .cert-tile que lo abrió. Añadir un
       certificado nuevo no requiere tocar este bloque. -->
  <div class="modal fade" id="certModal" tabindex="-1" aria-label="Certificado ampliado">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
        <img src="" class="w-100" id="certModalImg" alt="" loading="lazy" />
        <button type="button" class="cert-modal-nav cert-modal-prev" aria-label="Certificado anterior">&lsaquo;</button>
        <button type="button" class="cert-modal-nav cert-modal-next" aria-label="Certificado siguiente">&rsaquo;</button>
      </div>
    </div>
  </div>

  <!--  SERVICIOS  -->
  <div class="container" id="servicios">
    <section class="section-py">
      <div class="section-eyebrow reveal">
        <span class="num">05</span>
        <h2 class="section-title mb-0">servicios/</h2>
      </div>
      <p class="section-lede mb-4 reveal">Desarrollador web freelance en Murcia, disponible para proyectos en toda España. Esto es lo que puedo hacer por tu negocio.</p>

      <div class="services-grid">
        <div class="service-item reveal">
          <article class="service">
            <div class="service-head">
              <span class="service-index mono" aria-hidden="true">01</span>
              <h3 class="service-title">Diseño y desarrollo web a medida</h3>
              <p class="service-for">Para negocios que necesitan una web propia, rápida y que se encuentre en Google.</p>
            </div>
            <div class="service-detail">
              <ul class="timeline-list service-list">
                <li>Diseño y desarrollo <strong>a medida, sin CMS ni plantillas</strong>.</li>
                <li>Adaptada a móvil, con tema claro y oscuro.</li>
                <li><strong>SEO técnico</strong> desde el primer día: metadatos, datos estructurados y velocidad de carga.</li>
              </ul>
              <div class="project-tags service-tags">
                <span class="tag">HTML</span>
                <span class="tag">CSS</span>
                <span class="tag">JavaScript</span>
                <span class="tag">PHP</span>
              </div>
              <a href="#contact" class="cmd-link service-cta" data-asunto="Presupuesto · Web a medida">$ pedir_presupuesto</a>
            </div>
          </article>
        </div>

        <div class="service-item reveal">
          <article class="service">
            <div class="service-head">
              <span class="service-index mono" aria-hidden="true">02</span>
              <h3 class="service-title">Aplicaciones web a medida</h3>
              <p class="service-for">Para quien lleva su negocio en hojas de cálculo y necesita algo que no se descuadre.</p>
            </div>
            <div class="service-detail">
              <ul class="timeline-list service-list">
                <li>Panel de gestión con <strong>usuarios, roles y permisos</strong>.</li>
                <li>Base de datos propia e histórico auditable de cada movimiento.</li>
                <li><strong>API REST documentada</strong>, con tests y desplegada en tu servidor.</li>
              </ul>
              <div class="project-tags service-tags">
                <span class="tag">Java</span>
                <span class="tag">Spring Boot</span>
                <span class="tag">Angular</span>
                <span class="tag">PostgreSQL</span>
              </div>
              <a href="#contact" class="cmd-link service-cta" data-asunto="Presupuesto · Aplicación a medida">$ pedir_presupuesto</a>
            </div>
          </article>
        </div>

        <div class="service-item reveal">
          <article class="service">
            <div class="service-head">
              <span class="service-index mono" aria-hidden="true">03</span>
              <h3 class="service-title">Mantenimiento y optimización web</h3>
              <p class="service-for">Para webs que ya existen: si va lenta, no aparece en Google o hay algo roto.</p>
            </div>
            <div class="service-detail">
              <ul class="timeline-list service-list">
                <li>Auditoría de <strong>rendimiento, SEO y accesibilidad</strong>.</li>
                <li>Corrección de errores y cambios sobre lo que ya tienes.</li>
                <li>Actualizaciones, copias de seguridad e informe de cada intervención.</li>
              </ul>
              <div class="project-tags service-tags">
                <span class="tag">Auditoría</span>
                <span class="tag">SEO</span>
                <span class="tag">Rendimiento</span>
              </div>
              <a href="#contact" class="cmd-link service-cta" data-asunto="Presupuesto · Mantenimiento y optimización">$ pedir_presupuesto</a>
            </div>
          </article>
        </div>
      </div>
    </section>
  </div>

  <!--  CONTACTO  -->
  <div class="container mb-5" id="contact">
    <section class="section-py">
      <div class="section-eyebrow reveal">
        <span class="num">06</span>
        <h2 class="section-title mb-0">contacto/</h2>
      </div>
      <p class="section-lede mb-4 reveal">¿Tienes un proyecto en mente? Escríbeme y te respondo lo antes posible.</p>

      <?php if ($mensaje_enviado): ?>
        <div class="contact-window mb-4">
          <div class="alert alert-success alert-dismissible fade show mb-0" role="alert">
            <strong>¡Mensaje enviado!</strong> Gracias por contactarme. Me pondré en contacto contigo pronto.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        </div>
      <?php elseif (!empty($error_mensaje)): ?>
        <div class="contact-window mb-4">
          <div class="alert alert-danger alert-dismissible fade show mb-0" role="alert">
            <strong>Error:</strong> <?php echo htmlspecialchars($error_mensaje); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        </div>
      <?php endif; ?>
      <?php if (!$mensaje_enviado): ?>
        <form method="POST" class="win contact-window reveal">
          <div class="win-bar">
            <span class="win-dot"></span><span class="win-dot"></span><span class="win-dot"></span>
            <span class="win-name">contacto.form</span>
          </div>
          <div class="field-group">
            <input type="text" name="website" id="website" style="position: absolute; left: -9999px" tabindex="-1" autocomplete="off" aria-hidden="true" />
            <div class="field-row">
              <div>
                <label class="field-label" for="nombre">nombre</label>
                <input type="text" class="field" name="nombre" id="nombre" required />
              </div>
              <div>
                <label class="field-label" for="apellidos">apellidos</label>
                <input type="text" class="field" name="apellidos" id="apellidos" required />
              </div>
            </div>
            <div class="mb-3">
              <label class="field-label" for="email">email</label>
              <input type="email" class="field" name="email" id="email" required />
            </div>
            <div class="mb-3">
              <label class="field-label" for="asunto">asunto</label>
              <input type="text" class="field" name="asunto" id="asunto" required />
            </div>
            <div class="mb-4">
              <label class="field-label" for="mensaje">mensaje</label>
              <textarea class="field" id="mensaje" name="mensaje" rows="5" style="height: 150px" required></textarea>
            </div>
            <button type="submit" class="btn boton w-100">→ enviar</button>
          </div>
        </form>
      <?php endif; ?>
    </section>
  </div>
  </main>

  <div class="container-fluid mb-4">
    <footer class="row mt-4" style="border-top: 1px solid var(--border-soft); padding-top: 32px;">
      <div class="col-12 text-center d-flex flex-column justify-content-center align-items-center">
        <nav class="nav justify-content-center mb-3 align-items-center gap-2">
          <a class="nav-link icono" href="https://github.com/amartinezdev/" target="_blank" rel="noopener noreferrer" title="GitHub" aria-label="GitHub">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" class="bi bi-github" fill="currentColor" aria-hidden="true">
              <use href="#icon-github"></use>
            </svg>
          </a>
          <a class="nav-link icono" href="https://www.linkedin.com/in/alvaromartinezdev" target="_blank" rel="noopener noreferrer" title="LinkedIn" aria-label="LinkedIn">
            <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" class="bi bi-linkedin" fill="currentColor" aria-hidden="true">
              <use href="#icon-linkedin"></use>
            </svg>
          </a>
          <a class="nav-link icono" href="mailto:alvaromartinezdev@gmail.com" title="Correo electrónico" aria-label="Correo electrónico">
            <svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" class="bi bi-envelope-at-fill" fill="currentColor" aria-hidden="true">
              <use href="#icon-envelope"></use>
            </svg>
          </a>
        </nav>
        <p class="mb-3 mono" style="font-size: 0.8rem; color: var(--text-faint);">&copy; 2025 Álvaro Martínez. Casi todos los derechos reservados.</p>
      </div>
    </footer>
  </div>

  <button id="backToTop" class="back-to-top" aria-label="Volver arriba" title="Volver arriba" type="button">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" aria-hidden="true">
      <use href="#icon-arrow-up"></use>
    </svg>
  </button>

  <script src="assets/js/palabras.js" defer></script>
  <script src="assets/js/cambiarTema.js" defer></script>
  <script src="assets/js/backToTop.js" defer></script>
  <script src="assets/js/scrollspy.js" defer></script>
  <script src="assets/js/scrollReveal.js" defer></script>
  <script src="assets/js/heroReveal.js" defer></script>
  <script src="assets/js/certsMarquee.js" defer></script>
  <script src="assets/js/certModal.js" defer></script>
  <script src="assets/js/scrollProgress.js" defer></script>
  <script src="assets/js/timelineProgress.js" defer></script>
  <script src="assets/js/mouseSpotlight.js" defer></script>
  <script src="assets/js/textScramble.js" defer></script>
  <script src="assets/js/servicioAsunto.js" defer></script>
</body>

</html>
