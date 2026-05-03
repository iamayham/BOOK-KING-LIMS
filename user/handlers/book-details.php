<?php
// book-details.php - Display book information
$title = isset($_GET['title']) ? urldecode($_GET['title']) : 'Unknown Book';

// Fetch book details from Google Books API
$apiUrl = 'https://www.googleapis.com/books/v1/volumes?q=intitle:' . urlencode($title) . '&maxResults=1';
$response = @file_get_contents($apiUrl);
$bookData = $response ? json_decode($response, true) : null;
$book = $bookData['items'][0]['volumeInfo'] ?? null;

$bookTitle = trim((string) ($book['title'] ?? $title));
$bookTitle = $bookTitle !== '' ? $bookTitle : 'Unknown Book';
$authors = isset($book['authors']) && is_array($book['authors']) ? implode(', ', $book['authors']) : 'Unknown Author';
$publishedRaw = trim((string) ($book['publishedDate'] ?? ''));
$published = preg_match('/^\d{4}(-\d{2}(-\d{2})?)?$/', $publishedRaw) ? $publishedRaw : 'Unknown';
$description = trim((string) ($book['description'] ?? ''));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php $SITE_ICON_BASE = '../../'; require dirname(__DIR__, 2) . '/includes/site_head_icons.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Details: <?php echo htmlspecialchars($title); ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Montserrat', Arial, sans-serif;
            background: linear-gradient(180deg, #fdf8f4 0%, #fff 100%);
            color: #2b2b2b;
            min-height: 100vh;
            padding: 24px 16px;
        }
        .container {
            max-width: 980px;
            margin: 0 auto;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #b07154;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 18px;
        }
        .back-link:hover { text-decoration: underline; }
        .details-card {
            background: #fff;
            border: 1px solid rgba(176, 113, 84, 0.15);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(176, 113, 84, 0.08);
            padding: 22px;
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 22px;
        }
        .book-cover {
            width: 100%;
            aspect-ratio: 2 / 3;
            object-fit: cover;
            border-radius: 12px;
            background: #f3f3f3;
            border: 1px solid #eee;
        }
        .book-meta h1 {
            margin: 0 0 10px;
            font-size: 30px;
            color: #8f5b43;
            line-height: 1.2;
        }
        .meta-row {
            margin: 0 0 8px;
            font-size: 15px;
        }
        .meta-label {
            font-weight: 700;
            color: #5a5a5a;
        }
        .status-badge {
            display: inline-block;
            margin-top: 4px;
            padding: 6px 12px;
            border-radius: 999px;
            background: #e9f8ec;
            color: #2e7d32;
            font-weight: 700;
            font-size: 13px;
        }
        .description {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid #f0e1d9;
            color: #4d4d4d;
            line-height: 1.65;
            font-size: 14px;
        }
        .empty-state {
            background: #fff;
            border: 1px solid rgba(176, 113, 84, 0.15);
            border-radius: 16px;
            padding: 22px;
        }
        @media (max-width: 768px) {
            .details-card {
                grid-template-columns: 1fr;
            }
            .book-cover {
                max-width: 220px;
            }
            .book-meta h1 { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="./user-dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <?php if ($book): ?>
            <div class="details-card">
                <?php if (isset($book['imageLinks']['thumbnail'])): ?>
                    <img src="<?php echo str_replace('http://', 'https://', $book['imageLinks']['thumbnail']); ?>"
                         class="book-cover" alt="Book Cover">
                <?php else: ?>
                    <div class="book-cover"></div>
                <?php endif; ?>

                <div class="book-meta">
                    <h1><?php echo htmlspecialchars($bookTitle); ?></h1>
                    <p class="meta-row"><span class="meta-label">Author:</span> <?php echo htmlspecialchars($authors); ?></p>
                    <p class="meta-row"><span class="meta-label">Published:</span> <?php echo htmlspecialchars($published); ?></p>
                    <p class="meta-row"><span class="meta-label">Status:</span></p>
                    <span class="status-badge">Available</span>

                    <?php if ($description !== ''): ?>
                        <div class="description">
                            <span class="meta-label">Description:</span>
                            <?php echo htmlspecialchars($description); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h1><?php echo htmlspecialchars($bookTitle); ?></h1>
                <p>Detailed information about this book could not be loaded right now.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
