<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $siteName = 'CodeAgiles AfterSale AI';

        $seoTitle = 'WooCommerce AI Customer Support | CodeAgiles';

        $seoDescription =
            'AI-powered after-sales support for WooCommerce stores. Manage orders, customer tickets, email conversations, and returns in one platform.';

        $siteUrl = 'https://codeagiles-aftersale-ai-demo.ethiodeploy.app';

        $canonicalUrl = $siteUrl . '/';

        $ogImage = $siteUrl . '/og-image.png';

        $isPublicLandingPage = request()->path() === '/';
    @endphp

    <!-- Page Title -->
    <title>{{ $isPublicLandingPage ? $seoTitle : $siteName }}</title>

    @if ($isPublicLandingPage)
        <!-- Primary SEO -->
        <meta name="description" content="{{ $seoDescription }}">

        <meta name="robots" content="index, follow, max-image-preview:large">

        <link rel="canonical" href="{{ $canonicalUrl }}">

        <!-- Open Graph SEO -->
        <meta property="og:type" content="website">

        <meta property="og:site_name" content="{{ $siteName }}">

        <meta property="og:title" content="{{ $seoTitle }}">

        <meta property="og:description" content="{{ $seoDescription }}">

        <meta property="og:url" content="{{ $canonicalUrl }}">

        <meta property="og:image" content="{{ $ogImage }}">

        <meta property="og:image:alt" content="CodeAgiles AfterSale AI - Ecommerce Customer Support Platform">

        <meta property="og:locale" content="en_US">

        <!-- Twitter / X Cards -->
        <meta name="twitter:card" content="summary_large_image">

        <meta name="twitter:title" content="{{ $seoTitle }}">

        <meta name="twitter:description" content="{{ $seoDescription }}">

        <meta name="twitter:image" content="{{ $ogImage }}">

        <!-- Software Application Structured Data -->

        <script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'SoftwareApplication',
    'name' => $siteName,
    'applicationCategory' => 'BusinessApplication',
    'operatingSystem' => 'Web',
    'url' => $canonicalUrl,
    'description' => $seoDescription,
    'offers' => [
        '@type' => 'AggregateOffer',
        'priceCurrency' => 'USD',
        'lowPrice' => '29',
        'highPrice' => '119',
        'offerCount' => 3,
    ],
    'publisher' => [
        '@type' => 'Organization',
        'name' => 'CodeAgiles',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
    @else
        <!-- Prevent non-public pages from indexing -->
        <meta name="robots" content="noindex, nofollow">
    @endif

    <!-- Laravel Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>

<body>
    <div id="app"></div>
</body>

</html>
