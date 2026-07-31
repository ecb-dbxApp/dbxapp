param(
    [string]$FfmpegPath = '',
    [string]$NodePath = '',
    [switch]$ReuseSceneVideos
)

$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..\..')).Path
$workspaceParent = Split-Path -Parent $repoRoot

if ($FfmpegPath -eq '') {
    $bundledFfmpeg = Join-Path $workspaceParent 'dbxapp-cms-tutorials\node_modules\ffmpeg-static\ffmpeg.exe'
    if (Test-Path -LiteralPath $bundledFfmpeg) {
        $FfmpegPath = $bundledFfmpeg
    } else {
        $FfmpegPath = (Get-Command ffmpeg -ErrorAction Stop).Source
    }
}
if ($NodePath -eq '') {
    $NodePath = (Get-Command node -ErrorAction Stop).Source
}

function Format-Decimal([double]$Value) {
    return [string]::Format(
        [Globalization.CultureInfo]::InvariantCulture,
        '{0:0.000}',
        $Value
    )
}

function Get-Sha256([string]$Path) {
    $stream = [IO.File]::OpenRead($Path)
    $sha = [Security.Cryptography.SHA256]::Create()
    try {
        return ([BitConverter]::ToString($sha.ComputeHash($stream))).Replace('-', '')
    } finally {
        $sha.Dispose()
        $stream.Dispose()
    }
}

function New-ArgbColor([int]$Alpha, [string]$Hex) {
    $value = $Hex.TrimStart('#')
    return [Drawing.Color]::FromArgb(
        $Alpha,
        [Convert]::ToInt32($value.Substring(0, 2), 16),
        [Convert]::ToInt32($value.Substring(2, 2), 16),
        [Convert]::ToInt32($value.Substring(4, 2), 16)
    )
}

function New-OverlayCanvas([string]$Path, [scriptblock]$Paint) {
    $bitmap = [Drawing.Bitmap]::new(1024, 576, [Drawing.Imaging.PixelFormat]::Format32bppArgb)
    $graphics = [Drawing.Graphics]::FromImage($bitmap)
    try {
        $graphics.Clear([Drawing.Color]::Transparent)
        $graphics.SmoothingMode = [Drawing.Drawing2D.SmoothingMode]::AntiAlias
        $graphics.TextRenderingHint = [Drawing.Text.TextRenderingHint]::AntiAliasGridFit
        & $Paint $graphics
        $bitmap.Save($Path, [Drawing.Imaging.ImageFormat]::Png)
    } finally {
        $graphics.Dispose()
        $bitmap.Dispose()
    }
}

function Draw-Text(
    [Drawing.Graphics]$Graphics,
    [string]$Text,
    [float]$X,
    [float]$Y,
    [float]$Width,
    [float]$Height,
    [float]$Size,
    [string]$Color = '#FFFFFF',
    [switch]$Bold,
    [string]$Align = 'Near'
) {
    $style = if ($Bold) { [Drawing.FontStyle]::Bold } else { [Drawing.FontStyle]::Regular }
    $font = [Drawing.Font]::new('Arial', $Size, $style, [Drawing.GraphicsUnit]::Pixel)
    $brush = [Drawing.SolidBrush]::new((New-ArgbColor 255 $Color))
    $format = [Drawing.StringFormat]::new()
    try {
        $format.Alignment = [Drawing.StringAlignment]::$Align
        $format.LineAlignment = [Drawing.StringAlignment]::Center
        $format.Trimming = [Drawing.StringTrimming]::EllipsisCharacter
        $Graphics.DrawString(
            $Text,
            $font,
            $brush,
            [Drawing.RectangleF]::new($X, $Y, $Width, $Height),
            $format
        )
    } finally {
        $format.Dispose()
        $brush.Dispose()
        $font.Dispose()
    }
}

function New-CardOverlay(
    [string]$Path,
    [string]$Eyebrow,
    [string]$Title,
    [string]$Subtitle,
    [int]$X,
    [int]$Y,
    [int]$Width,
    [int]$Height,
    [string]$Accent = '#22D3EE',
    [int]$TitleSize = 35,
    [switch]$Centered
) {
    New-OverlayCanvas $Path {
        param($graphics)
        $shadow = [Drawing.SolidBrush]::new((New-ArgbColor 95 '#000000'))
        $surface = [Drawing.SolidBrush]::new((New-ArgbColor 228 '#061B3C'))
        $accentBrush = [Drawing.SolidBrush]::new((New-ArgbColor 245 $Accent))
        $hairline = [Drawing.Pen]::new((New-ArgbColor 178 '#7DD3FC'), 1.5)
        try {
            $graphics.FillRectangle($shadow, $X + 10, $Y + 12, $Width, $Height)
            $graphics.FillRectangle($surface, $X, $Y, $Width, $Height)
            $graphics.FillRectangle($accentBrush, $X, $Y, 8, $Height)
            $graphics.DrawRectangle($hairline, $X + 0.75, $Y + 0.75, $Width - 1.5, $Height - 1.5)
        } finally {
            $hairline.Dispose()
            $accentBrush.Dispose()
            $surface.Dispose()
            $shadow.Dispose()
        }
        $alignment = if ($Centered) { 'Center' } else { 'Near' }
        $textX = if ($Centered) { $X + 22 } else { $X + 30 }
        $textWidth = if ($Centered) { $Width - 44 } else { $Width - 48 }
        if ($Eyebrow -ne '') {
            Draw-Text $graphics $Eyebrow $textX ($Y + 18) $textWidth 30 17 '#7DD3FC' -Bold -Align $alignment
        }
        Draw-Text $graphics $Title $textX ($Y + 48) $textWidth ([Math]::Max(48, $Height - 92)) $TitleSize '#FFFFFF' -Bold -Align $alignment
        if ($Subtitle -ne '') {
            Draw-Text $graphics $Subtitle $textX ($Y + $Height - 46) $textWidth 28 17 '#DBEAFE' -Align $alignment
        }
    }
}

function New-ChipOverlay(
    [string]$Path,
    [string]$Text,
    [int]$X,
    [int]$Y,
    [int]$Width,
    [string]$Accent = '#22D3EE',
    [string]$Marker = 'LIVE'
) {
    New-OverlayCanvas $Path {
        param($graphics)
        $shadow = [Drawing.SolidBrush]::new((New-ArgbColor 88 '#000000'))
        $surface = [Drawing.SolidBrush]::new((New-ArgbColor 224 '#071A36'))
        $accentBrush = [Drawing.SolidBrush]::new((New-ArgbColor 245 $Accent))
        $dot = [Drawing.SolidBrush]::new((New-ArgbColor 255 '#FFFFFF'))
        try {
            $graphics.FillRectangle($shadow, $X + 7, $Y + 8, $Width, 64)
            $graphics.FillRectangle($surface, $X, $Y, $Width, 64)
            $graphics.FillRectangle($accentBrush, $X, $Y, 6, 64)
            $graphics.FillEllipse($accentBrush, $X + 22, $Y + 23, 18, 18)
            $graphics.FillEllipse($dot, $X + 28, $Y + 29, 6, 6)
        } finally {
            $dot.Dispose()
            $accentBrush.Dispose()
            $surface.Dispose()
            $shadow.Dispose()
        }
        Draw-Text $graphics $Text ($X + 52) ($Y + 7) ($Width - 128) 50 22 '#FFFFFF' -Bold
        Draw-Text $graphics $Marker ($X + $Width - 80) ($Y + 13) 62 38 13 '#7DD3FC' -Bold -Align 'Center'
    }
}

function New-PulseOverlay(
    [string]$Path,
    [int]$CenterX,
    [int]$CenterY,
    [int]$Radius,
    [string]$Accent = '#22D3EE'
) {
    New-OverlayCanvas $Path {
        param($graphics)
        $outer = [Drawing.Pen]::new((New-ArgbColor 94 $Accent), 3)
        $middle = [Drawing.Pen]::new((New-ArgbColor 152 $Accent), 2)
        $inner = [Drawing.Pen]::new((New-ArgbColor 224 '#FFFFFF'), 2)
        $core = [Drawing.SolidBrush]::new((New-ArgbColor 245 $Accent))
        try {
            $graphics.DrawEllipse($outer, $CenterX - $Radius, $CenterY - $Radius, $Radius * 2, $Radius * 2)
            $graphics.DrawEllipse($middle, $CenterX - [int]($Radius * .66), $CenterY - [int]($Radius * .66), [int]($Radius * 1.32), [int]($Radius * 1.32))
            $graphics.DrawEllipse($inner, $CenterX - [int]($Radius * .34), $CenterY - [int]($Radius * .34), [int]($Radius * .68), [int]($Radius * .68))
            $graphics.FillEllipse($core, $CenterX - 7, $CenterY - 7, 14, 14)
        } finally {
            $core.Dispose()
            $inner.Dispose()
            $middle.Dispose()
            $outer.Dispose()
        }
    }
}

function New-AnimatedScene([hashtable]$Scene, [int]$Index) {
    $duration = Format-Decimal ([double]$Scene.Duration)
    $target = Join-Path $sceneDir ('scene-{0:D2}.mp4' -f $Index)
    if ($ReuseSceneVideos -and (Test-Path -LiteralPath $target -PathType Leaf)) {
        return $target
    }

    $args = @('-hide_banner', '-loglevel', 'error', '-y')
    $args += @('-loop', '1', '-framerate', '30', '-t', $duration, '-i', $Scene.Background)
    foreach ($element in $Scene.Elements) {
        $args += @('-loop', '1', '-framerate', '30', '-t', $duration, '-i', $element.File)
    }

    $shade = Format-Decimal ([double]$Scene.Shade)
    $filters = @(
        "[0:v]scale=1024:576:force_original_aspect_ratio=increase,crop=1024:576," +
        "eq=contrast=1.045:saturation=1.08,drawbox=x=0:y=0:w=iw:h=ih:color=0x03152f@${shade}:t=fill[base0]"
    )
    $baseLabel = 'base0'

    for ($elementIndex = 0; $elementIndex -lt $Scene.Elements.Count; $elementIndex++) {
        $inputIndex = $elementIndex + 1
        $element = $Scene.Elements[$elementIndex]
        $start = Format-Decimal ([double]$element.Start)
        $end = Format-Decimal ([double]$element.End)
        $fadeValue = if ($null -ne $element.Fade) { [double]$element.Fade } else { 0.20 }
        $fade = Format-Decimal $fadeValue
        $elementLabel = 'el' + $elementIndex
        $nextBase = 'base' + ($elementIndex + 1)

        if ($element.Kind -eq 'panel') {
            $panelWidth = [int]$element.Width
            $panelHeight = [int]$element.Height
            $filters += "[$inputIndex`:v]scale=$panelWidth`:$panelHeight`:force_original_aspect_ratio=decrease," +
                "pad=$panelWidth`:$panelHeight`:(ow-iw)/2`:(oh-ih)/2`:color=0x071a36," +
                "drawbox=x=0:y=0:w=iw:h=ih:color=0x38bdf8@0.92:t=3,format=rgba," +
                "fade=t=in:st=$start`:d=$fade`:alpha=1,fade=t=out:st=$end`:d=$fade`:alpha=1[$elementLabel]"
            $filters += "[$baseLabel][$elementLabel]overlay=$($element.X):$($element.Y):shortest=1:format=auto[$nextBase]"
        } else {
            $filters += "[$inputIndex`:v]scale=1024:576:flags=lanczos,format=rgba," +
                "fade=t=in:st=$start`:d=$fade`:alpha=1,fade=t=out:st=$end`:d=$fade`:alpha=1[$elementLabel]"
            $filters += "[$baseLabel][$elementLabel]overlay=0:0:shortest=1:format=auto[$nextBase]"
        }
        $baseLabel = $nextBase
    }

    $fadeOutStart = Format-Decimal ([double]$Scene.Duration - 0.08)
    $filters += "[$baseLabel]fade=t=in:st=0:d=0.08,fade=t=out:st=$fadeOutStart`:d=0.08," +
        "format=yuv420p[outv]"

    $args += @(
        '-filter_complex', ($filters -join ';'),
        '-map', '[outv]',
        '-an',
        '-t', $duration,
        '-c:v', 'libx264',
        '-preset', 'medium',
        '-crf', '16',
        '-r', '30',
        '-pix_fmt', 'yuv420p',
        $target
    )

    & $FfmpegPath @args
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $target -PathType Leaf)) {
        throw "Filmszene $Index konnte nicht gerendert werden."
    }
    return $target
}

if (-not (Test-Path -LiteralPath $FfmpegPath -PathType Leaf)) {
    throw "FFmpeg wurde nicht gefunden: $FfmpegPath"
}
if (-not (Test-Path -LiteralPath $NodePath -PathType Leaf)) {
    throw "Node.js wurde nicht gefunden: $NodePath"
}

Add-Type -AssemblyName System.Drawing

$width = 1024
$height = 576
$workDir = Join-Path $repoRoot 'files\tmp\dbxapp-tvspot-20260731-v4'
$graphicDir = Join-Path $workDir 'graphics'
$sceneDir = Join-Path $workDir 'scenes'
$videoDir = Join-Path $repoRoot 'files\media\video'
$imageDir = Join-Path $repoRoot 'files\media\img\images'
$heroDir = Join-Path $repoRoot 'files\media\img\hero'
$galleryDir = Join-Path $repoRoot 'files\media\img\gallery'

$videoFile = Join-Path $videoDir 'dbxapp-tvspot-20260731-v4.mp4'
$posterFile = Join-Path $imageDir 'dbxapp-tvspot-poster-20260731-v4.webp'
$manifestFile = Join-Path $videoDir 'dbxapp-tvspot-20260731-v4-manifest.json'
$licenseFile = Join-Path $videoDir 'dbxapp-tvspot-20260731-v4-license.txt'
$musicFile = Join-Path $workDir 'dbxapp-electro-disco-original-126bpm.wav'
$musicGenerator = Join-Path $PSScriptRoot 'generate_home_tvspot_music_v3_20260730.mjs'

$originalLogo = Join-Path $imageDir 'dbxapp-original-logo-20260730.png'
$shopSource = Join-Path $imageDir 'dbxapp-tvspot-source-shop-active-20260730.png'
$healthSource = Join-Path $imageDir 'dbxapp-tvspot-source-health-100-20260730.png'
$cmsSource = Join-Path $galleryDir 'tutorial-cms-uebersicht.webp'
$databaseSource = Join-Path $galleryDir 'tutorial-admin-dashboard-10-datenbanken.webp'
$workflowSource = Join-Path $galleryDir 'tutorial-workflow-nutzen-01-start.webp'

$mobileHero = Join-Path $heroDir 'dbxapp-info-mobile.webp'
$desktopHero = Join-Path $heroDir 'dbxapp-info-desktop.webp'
$kiHero = Join-Path $heroDir 'dbxapp-info-ki.webp'
$cmsHero = Join-Path $heroDir 'dbxapp-info-cms.webp'
$systemHero = Join-Path $heroDir 'dbxapp-dashboard-systemblick-web.webp'
$platformHero = Join-Path $heroDir 'dbxapp-platform-hero-20260728.webp'
$modularHero = Join-Path $imageDir 'dbxapp-modular-wachsen-20260728.webp'
$brandHero = Join-Path $imageDir 'dbxapp-eine-plattform-alle-moeglichkeiten.webp'

New-Item -ItemType Directory -Force -Path $workDir, $graphicDir, $sceneDir, $videoDir, $imageDir | Out-Null

$requiredSources = @(
    $originalLogo, $shopSource, $healthSource, $cmsSource, $databaseSource,
    $workflowSource, $mobileHero, $desktopHero, $kiHero, $cmsHero,
    $systemHero, $platformHero, $modularHero, $brandHero, $musicGenerator
)
foreach ($source in $requiredSources) {
    if (-not (Test-Path -LiteralPath $source -PathType Leaf)) {
        throw "Szenenquelle fehlt: $source"
    }
}

& $NodePath $musicGenerator $musicFile
if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $musicFile -PathType Leaf)) {
    throw 'Der originale Electro-Disco-Track konnte nicht erzeugt werden.'
}

function Graphic([string]$Name) {
    return Join-Path $graphicDir ($Name + '.png')
}

# 01 – Auftakt: Kanäle erscheinen, der gemeinsame Kern bleibt stehen.
New-CardOverlay (Graphic '01-title') 'DBXAPP' 'MEHR KANÄLE.' 'HANDY · DESKTOP · KI · CMS · SHOP · DATEN' 70 70 884 170 '#22D3EE' 44
New-ChipOverlay (Graphic '01-mobile') 'HANDY + DESKTOP' 94 286 354 '#22D3EE' 'VERBUNDEN'
New-ChipOverlay (Graphic '01-content') 'KI + CMS + SHOP' 576 286 354 '#60A5FA' 'AKTIV'
New-CardOverlay (Graphic '01-core') '' 'EIN SYSTEM.' 'EIN GEMEINSAMER KERN.' 248 386 528 130 '#F43F5E' -Centered

# 02 – Mobile Ereignisse.
New-CardOverlay (Graphic '02-title') 'HANDY' 'ÜBERALL DABEI' 'Die Plattform bleibt verbunden.' 48 390 430 140 '#22D3EE'
New-ChipOverlay (Graphic '02-ready') 'MOBIL BEREIT' 652 88 304 '#22D3EE' 'OK'
New-ChipOverlay (Graphic '02-login') 'SICHER ANGEMELDET' 604 184 352 '#60A5FA' 'OK'
New-ChipOverlay (Graphic '02-sync') 'DATEN SYNCHRON' 638 280 318 '#F43F5E' 'LIVE'

# 03 – Desktop: drei Bereiche bauen den Überblick auf.
New-CardOverlay (Graphic '03-title') 'DESKTOP' 'VOLLER ÜBERBLICK' 'Ein Fenster. Alle Bereiche.' 48 390 500 140 '#60A5FA' 32
New-ChipOverlay (Graphic '03-cms') 'CMS' 618 86 300 '#22D3EE' 'BEREIT'
New-ChipOverlay (Graphic '03-shop') 'SHOP' 650 180 268 '#60A5FA' 'AKTIV'
New-ChipOverlay (Graphic '03-flow') 'WORKFLOW' 586 274 332 '#F43F5E' 'LÄUFT'

# 04 – KI: Signalringe und klar getrennte Zustände.
New-CardOverlay (Graphic '04-title') 'KI' 'IDEEN WERDEN PROZESSE' 'Erkennen. Entscheiden. Ausführen.' 48 390 540 140 '#22D3EE' 29
New-PulseOverlay (Graphic '04-pulse-a') 510 250 78 '#22D3EE'
New-PulseOverlay (Graphic '04-pulse-b') 510 250 134 '#60A5FA'
New-PulseOverlay (Graphic '04-pulse-c') 510 250 194 '#F43F5E'
New-ChipOverlay (Graphic '04-detect') 'ERKENNEN' 74 90 278 '#22D3EE' '01'
New-ChipOverlay (Graphic '04-decide') 'ENTSCHEIDEN' 628 90 330 '#60A5FA' '02'
New-ChipOverlay (Graphic '04-run') 'AUSFÜHREN' 676 292 282 '#F43F5E' '03'

# 05 – CMS und Shop wechseln als echte Ereignisse auf derselben Bühne.
New-CardOverlay (Graphic '05-cms-title') 'CMS' 'INHALTE DIREKT' 'Seiten und Medien im System.' 24 180 246 188 '#22D3EE' 25
New-CardOverlay (Graphic '05-shop-title') 'SHOP' 'AKTIV VERKAUFEN' 'Produkte und Kanäle verbunden.' 24 180 246 188 '#F43F5E' 25

# 06 – Daten, Health und Workflow werden nacheinander sichtbar.
New-CardOverlay (Graphic '06-db-title') 'DATENBANK' 'DATEN KLAR' 'Struktur statt Insellösung.' 24 180 246 188 '#22D3EE' 25
New-CardOverlay (Graphic '06-health-title') 'SYSTEM HEALTH' '100 %' 'Der Betrieb bleibt im Blick.' 24 180 246 188 '#60A5FA' 25
New-CardOverlay (Graphic '06-flow-title') 'WORKFLOW' 'ALLES VERBUNDEN' 'Vom Schritt zum Ergebnis.' 24 180 246 188 '#F43F5E' 25

# 07 – Modularität: Bausteine erscheinen um den stabilen Kern.
New-CardOverlay (Graphic '07-title') 'DBXAPP' 'MODULAR WACHSEN' 'Neue Aufgaben. Derselbe Kern.' 48 390 500 140 '#22D3EE' 31
New-ChipOverlay (Graphic '07-content') 'CONTENT' 74 84 262 '#22D3EE' 'MODUL'
New-ChipOverlay (Graphic '07-shop') 'SHOP' 686 84 262 '#60A5FA' 'MODUL'
New-ChipOverlay (Graphic '07-data') 'DATEN' 74 188 262 '#F43F5E' 'MODUL'
New-ChipOverlay (Graphic '07-flow') 'WORKFLOW' 686 188 262 '#22D3EE' 'MODUL'

# 08 – Auflösung: die Botschaft baut sich in drei Takten auf.
New-CardOverlay (Graphic '08-many') '' 'VIELE AUFGABEN.' '' 172 112 680 112 '#22D3EE' -Centered
New-CardOverlay (Graphic '08-core') '' 'EIN GEMEINSAMER KERN.' '' 120 252 784 112 '#60A5FA' -Centered
New-CardOverlay (Graphic '08-solution') 'DBXAPP' 'DIE LÖSUNG' '' 232 392 560 126 '#F43F5E' -Centered

# 09 – Finale: ruhiges Logo, starke Schlusszeile und ein kurzer Lichtimpuls.
New-CardOverlay (Graphic '09-tagline') '' 'MODULAR. SICHER. PASSEND.' '' 156 426 712 96 '#22D3EE' -Centered
New-PulseOverlay (Graphic '09-pulse') 512 288 244 '#22D3EE'

$scenes = @(
    @{ Background = $platformHero; Duration = 3.20; Shade = 0.24; Elements = @(
        @{ Kind = 'graphic'; File = (Graphic '01-title'); Start = 0.08; End = 2.92; Fade = 0.24 },
        @{ Kind = 'graphic'; File = (Graphic '01-mobile'); Start = 0.44; End = 1.48; Fade = 0.18 },
        @{ Kind = 'graphic'; File = (Graphic '01-content'); Start = 1.08; End = 2.14; Fade = 0.18 },
        @{ Kind = 'graphic'; File = (Graphic '01-core'); Start = 1.78; End = 2.94; Fade = 0.22 }
    )},
    @{ Background = $mobileHero; Duration = 3.20; Shade = 0.12; Elements = @(
        @{ Kind = 'graphic'; File = (Graphic '02-title'); Start = 0.08; End = 3.00; Fade = 0.22 },
        @{ Kind = 'graphic'; File = (Graphic '02-ready'); Start = 0.38; End = 1.24; Fade = 0.16 },
        @{ Kind = 'graphic'; File = (Graphic '02-login'); Start = 1.08; End = 2.02; Fade = 0.16 },
        @{ Kind = 'graphic'; File = (Graphic '02-sync'); Start = 1.86; End = 2.92; Fade = 0.16 }
    )},
    @{ Background = $desktopHero; Duration = 3.20; Shade = 0.14; Elements = @(
        @{ Kind = 'graphic'; File = (Graphic '03-title'); Start = 0.08; End = 3.00; Fade = 0.22 },
        @{ Kind = 'graphic'; File = (Graphic '03-cms'); Start = 0.34; End = 1.22; Fade = 0.16 },
        @{ Kind = 'graphic'; File = (Graphic '03-shop'); Start = 1.02; End = 1.94; Fade = 0.16 },
        @{ Kind = 'graphic'; File = (Graphic '03-flow'); Start = 1.76; End = 2.94; Fade = 0.16 }
    )},
    @{ Background = $kiHero; Duration = 3.20; Shade = 0.10; Elements = @(
        @{ Kind = 'graphic'; File = (Graphic '04-title'); Start = 0.08; End = 3.00; Fade = 0.22 },
        @{ Kind = 'graphic'; File = (Graphic '04-pulse-a'); Start = 0.18; End = 1.04; Fade = 0.18 },
        @{ Kind = 'graphic'; File = (Graphic '04-pulse-b'); Start = 0.82; End = 1.82; Fade = 0.18 },
        @{ Kind = 'graphic'; File = (Graphic '04-pulse-c'); Start = 1.60; End = 2.84; Fade = 0.18 },
        @{ Kind = 'graphic'; File = (Graphic '04-detect'); Start = 0.34; End = 1.26; Fade = 0.16 },
        @{ Kind = 'graphic'; File = (Graphic '04-decide'); Start = 1.06; End = 2.00; Fade = 0.16 },
        @{ Kind = 'graphic'; File = (Graphic '04-run'); Start = 1.82; End = 2.94; Fade = 0.16 }
    )},
    @{ Background = $cmsHero; Duration = 4.00; Shade = 0.24; Elements = @(
        @{ Kind = 'panel'; File = $cmsSource; Start = 0.12; End = 1.78; Fade = 0.22; X = 286; Y = 76; Width = 700; Height = 414 },
        @{ Kind = 'graphic'; File = (Graphic '05-cms-title'); Start = 0.18; End = 1.72; Fade = 0.20 },
        @{ Kind = 'panel'; File = $shopSource; Start = 1.82; End = 3.76; Fade = 0.22; X = 286; Y = 76; Width = 700; Height = 414 },
        @{ Kind = 'graphic'; File = (Graphic '05-shop-title'); Start = 1.90; End = 3.72; Fade = 0.20 }
    )},
    @{ Background = $systemHero; Duration = 4.00; Shade = 0.28; Elements = @(
        @{ Kind = 'panel'; File = $databaseSource; Start = 0.08; End = 1.34; Fade = 0.18; X = 286; Y = 76; Width = 700; Height = 414 },
        @{ Kind = 'graphic'; File = (Graphic '06-db-title'); Start = 0.12; End = 1.30; Fade = 0.16 },
        @{ Kind = 'panel'; File = $healthSource; Start = 1.26; End = 2.62; Fade = 0.18; X = 286; Y = 76; Width = 700; Height = 414 },
        @{ Kind = 'graphic'; File = (Graphic '06-health-title'); Start = 1.32; End = 2.58; Fade = 0.16 },
        @{ Kind = 'panel'; File = $workflowSource; Start = 2.54; End = 3.82; Fade = 0.18; X = 286; Y = 76; Width = 700; Height = 414 },
        @{ Kind = 'graphic'; File = (Graphic '06-flow-title'); Start = 2.60; End = 3.78; Fade = 0.16 }
    )},
    @{ Background = $modularHero; Duration = 3.20; Shade = 0.16; Elements = @(
        @{ Kind = 'graphic'; File = (Graphic '07-title'); Start = 0.08; End = 3.00; Fade = 0.22 },
        @{ Kind = 'graphic'; File = (Graphic '07-content'); Start = 0.26; End = 2.88; Fade = 0.16 },
        @{ Kind = 'graphic'; File = (Graphic '07-shop'); Start = 0.72; End = 2.88; Fade = 0.16 },
        @{ Kind = 'graphic'; File = (Graphic '07-data'); Start = 1.18; End = 2.88; Fade = 0.16 },
        @{ Kind = 'graphic'; File = (Graphic '07-flow'); Start = 1.64; End = 2.88; Fade = 0.16 }
    )},
    @{ Background = $brandHero; Duration = 2.80; Shade = 0.34; Elements = @(
        @{ Kind = 'graphic'; File = (Graphic '08-many'); Start = 0.08; End = 0.96; Fade = 0.18 },
        @{ Kind = 'graphic'; File = (Graphic '08-core'); Start = 0.72; End = 1.72; Fade = 0.18 },
        @{ Kind = 'graphic'; File = (Graphic '08-solution'); Start = 1.44; End = 2.62; Fade = 0.20 }
    )},
    @{ Background = $originalLogo; Duration = 3.20; Shade = 0.02; Elements = @(
        @{ Kind = 'graphic'; File = (Graphic '09-pulse'); Start = 0.10; End = 1.22; Fade = 0.26 },
        @{ Kind = 'graphic'; File = (Graphic '09-tagline'); Start = 0.92; End = 3.02; Fade = 0.28 }
    )}
)

$sceneFiles = @()
for ($index = 0; $index -lt $scenes.Count; $index++) {
    $sceneFiles += New-AnimatedScene $scenes[$index] ($index + 1)
}

$finalArgs = @('-hide_banner', '-loglevel', 'error', '-y')
foreach ($sceneFile in $sceneFiles) {
    $finalArgs += @('-i', $sceneFile)
}
$finalArgs += @('-i', $musicFile)

$concatInputs = ''
for ($index = 0; $index -lt $sceneFiles.Count; $index++) {
    $concatInputs += "[$index`:v]"
}
$musicInput = $sceneFiles.Count
$finalFilter = $concatInputs + "concat=n=$($sceneFiles.Count):v=1:a=0," +
    "trim=duration=30,setpts=PTS-STARTPTS,vignette=PI/8," +
    "eq=contrast=1.035:saturation=1.06,unsharp=5`:5`:0.22,format=yuv420p[outv];" +
    "[$musicInput`:a]atrim=duration=30,asetpts=PTS-STARTPTS," +
    "afade=t=in`:st=0`:d=0.08,afade=t=out`:st=29.25`:d=0.75," +
    "loudnorm=I=-13.0`:LRA=7`:TP=-1.1[outa]"

$finalArgs += @(
    '-filter_complex', $finalFilter,
    '-map', '[outv]',
    '-map', '[outa]',
    '-t', '30',
    '-c:v', 'libx264',
    '-preset', 'medium',
    '-crf', '18',
    '-maxrate', '2200k',
    '-bufsize', '4400k',
    '-r', '30',
    '-pix_fmt', 'yuv420p',
    '-profile:v', 'high',
    '-level', '4.0',
    '-c:a', 'aac',
    '-b:a', '192k',
    '-ar', '48000',
    '-movflags', '+faststart',
    '-shortest',
    $videoFile
)

& $FfmpegPath @finalArgs
if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $videoFile -PathType Leaf)) {
    throw 'Die vierte TV-Spot-Fassung konnte nicht gerendert werden.'
}

& $FfmpegPath -hide_banner -loglevel error -y `
    -ss 29.10 -i $videoFile `
    -frames:v 1 -vf 'scale=1024:576:flags=lanczos' `
    -c:v libwebp -quality 93 $posterFile
if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $posterFile -PathType Leaf)) {
    throw 'Das Poster der vierten Fassung konnte nicht erstellt werden.'
}

$previewTimes = @(1.0, 3.9, 7.0, 10.4, 13.4, 15.3, 17.4, 20.0, 23.2, 25.2, 27.6, 29.2)
for ($index = 0; $index -lt $previewTimes.Count; $index++) {
    $preview = Join-Path $workDir ('preview-{0:D2}.png' -f ($index + 1))
    & $FfmpegPath -hide_banner -loglevel error -y `
        -ss $previewTimes[$index] -i $videoFile `
        -frames:v 1 -vf 'scale=512:288:flags=lanczos' $preview
    if ($LASTEXITCODE -ne 0) {
        throw "Vorschau $($index + 1) konnte nicht erstellt werden."
    }
}

$contactSheet = Join-Path $workDir 'timeline-contactsheet.png'
& $FfmpegPath -hide_banner -loglevel error -y `
    -i $videoFile `
    -vf 'fps=1/2.5,scale=512:288:flags=lanczos,tile=4x3:padding=8:margin=8:color=0x061b3c' `
    -frames:v 1 $contactSheet
if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $contactSheet -PathType Leaf)) {
    throw 'Der Timeline-Kontaktbogen konnte nicht erstellt werden.'
}

$videoInfo = Get-Item -LiteralPath $videoFile
$videoHash = Get-Sha256 $videoFile
$posterHash = Get-Sha256 $posterFile
$manifest = [ordered]@{
    artifact = $videoInfo.Name
    created = '2026-07-31'
    purpose = 'Vierte, ereignisbasierte TV-Spot-Fassung fuer die deutsche dbxapp-Startseite'
    technical = [ordered]@{
        duration_seconds = 30
        video_codec = 'h264'
        resolution = '1024x576'
        frame_rate = 30
        audio_codec = 'aac'
        audio_sample_rate = 48000
        audio_channels = 2
        file_size_bytes = $videoInfo.Length
        sha256 = $videoHash
    }
    poster = [ordered]@{
        path = 'files/media/img/images/dbxapp-tvspot-poster-20260731-v4.webp'
        resolution = '1024x576'
        sha256 = $posterHash
    }
    direction = [ordered]@{
        camera = 'Stabile Bildwelten ohne Zoompan, Schwenk oder permanente Verschiebung'
        action = 'Ein- und Ausblendungen von Statuskarten, UI-Panels, Signalringen und Kernbotschaften'
        edit = 'Neun beatgenaue Kapitel mit kurzen dunklen Impulsen an den Schnitten'
        story = 'Kanaele -> Geraete -> KI -> CMS/Shop -> Daten/Workflow -> modularer Kern -> Marke'
    }
    visual_changes = @(
        'Alle Zoompan- und Kamerafahrt-Filter entfernt',
        'Mehrstufige Ein- und Ausblendungen statt Mikroverschiebungen',
        'CMS und Shop wechseln als eigenstaendige UI-Ereignisse',
        'Datenbank, System Health und Workflow erscheinen als aufeinanderfolgende Zustandswechsel',
        'KI-Szene mit drei Signalimpulsen und klarer Erkennen-Entscheiden-Ausfuehren-Choreografie',
        'Ruhiger Markenabschluss mit Lichtimpuls und stabil erkennbarem Original-Logo'
    )
    music = [ordered]@{
        origin = 'Originalkomposition fuer dbxapp'
        generator = 'dbx/modules/dbxContent_admin/tools/generate_home_tvspot_music_v3_20260730.mjs'
        tempo_bpm = 126
        third_party_audio = $false
        third_party_samples = $false
    }
    build = 'dbx/modules/dbxContent_admin/tools/build_home_tvspot_v4_20260731.ps1'
    preview = 'files/tmp/dbxapp-tvspot-20260731-v4/timeline-contactsheet.png'
    previous_version_retained = 'files/media/video/dbxapp-tvspot-20260730-v3.mp4'
}
$manifest | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath $manifestFile -Encoding utf8

$license = @'
dbxapp TV-Spot 2026-07-31 – Fassung v4
========================================

Video und Motion Design:
  dbxapp-tvspot-20260731-v4.mp4

  Die Animationen wurden lokal und deterministisch aus dbxapp-eigenen
  Bildquellen erzeugt. Die Bildwelten bleiben statisch; die Bewegung entsteht
  aus eigens erzeugten Statuskarten, Signalringen, Text- und UI-Einblendungen.

Musik:
  Eigens fuer dbxapp erzeugte Electro-Disco-Komposition, 126 BPM.
  Keine Drittanbieter-Aufnahme, keine Samples, kein fremder Gesang und keine
  uebernommene Melodie.

Logo:
  files/media/img/images/dbxapp-original-logo-20260730.png

Bildquellen:
  Ausschliesslich lokale dbxapp-Projektmedien und lokale Ansichten des Systems.
'@
$license | Set-Content -LiteralPath $licenseFile -Encoding utf8

Write-Output "Video: $videoFile"
Write-Output "Poster: $posterFile"
Write-Output "Kontaktbogen: $contactSheet"
Write-Output "Manifest: $manifestFile"
Write-Output "Provenienz: $licenseFile"
