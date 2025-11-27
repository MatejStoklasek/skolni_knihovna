<?php
session_start();
define('ACCESS_ALLOWED', true);
require_once 'config.php';

$conn = getDBConnection();
$error = '';
$success = '';

// Přidání nového žáka
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pridat_zaka'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Neplatný bezpečnostní token!";
    } else {
        $jmeno = sanitizeInput($_POST['jmeno'] ?? '');
        $prijmeni = sanitizeInput($_POST['prijmeni'] ?? '');
        $trida = sanitizeInput($_POST['trida'] ?? '');

        if (empty($jmeno) || empty($prijmeni) || empty($trida)) {
            $error = "Všechna pole musí být vyplněna!";
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO zak_zakaznici (jmeno, prijmeni, trida) VALUES (?, ?, ?)");
                $stmt->execute([$jmeno, $prijmeni, $trida]);

                header("Location: zakaznici.php?success=1");
                exit();
            } catch (PDOException $e) {
                error_log("Chyba při přidání žáka: " . $e->getMessage());
                $error = "Chyba při přidávání žáka.";
            }
        }
    }
}

// Smazání žáka
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['smazat_zaka'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Neplatný bezpečnostní token!";
    } else {
        $zakID = filter_var($_POST['zak_id'] ?? '', FILTER_VALIDATE_INT);

        if ($zakID) {
            try {
                // Zkontroluj, zda nemá žák aktivní půjčení
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM pujceni WHERE zakID = ?");
                $stmt->execute([$zakID]);
                $pujceni = $stmt->fetch()['count'];

                if ($pujceni > 0) {
                    $error = "Žáka nelze smazat, má aktivní půjčení!";
                } else {
                    $stmt = $conn->prepare("DELETE FROM zak_zakaznici WHERE zakID = ?");
                    $stmt->execute([$zakID]);

                    header("Location: zakaznici.php?deleted=1");
                    exit();
                }
            } catch (PDOException $e) {
                error_log("Chyba při mazání žáka: " . $e->getMessage());
                $error = "Chyba při mazání žáka.";
            }
        }
    }
}

// Vyhledávání
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$searchParam = "%" . $search . "%";

if (isset($_GET['success'])) $success = "Žák byl úspěšně přidán!";
if (isset($_GET['deleted'])) $success = "Žák byl úspěšně smazán!";
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zákazníci - Virtuální Knihovna</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <header>
        <h1>📚 Virtuální Knihovna</h1>
        <nav>
            <a href="main.php">Domů</a>
            <a href="knihy.php">Knihy</a>
            <a href="pujceni.php">Půjčení</a>
            <a href="zakaznici.php" class="active">Zákazníci</a>
            <a href="zamestnanci.php">Zaměstnanci</a>
        </nav>
    </header>

    <main>
        <section class="page-header">
            <h2>👥 Správa zákazníků (žáků)</h2>
            <p>Přehled všech registrovaných žáků</p>
        </section>

        <?php if($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="action-bar">
            <form method="GET" class="search-form">
                <label for="search" class="sr-only">Hledat žáka</label>
                <input type="text" id="search" name="search" placeholder="Hledat žáka..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">🔍 Hledat</button>
            </form>
            <button type="button" onclick="openModal('addStudentModal')" class="btn-primary">
                ➕ Přidat žáka
            </button>
        </div>

        <section class="content-section">
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Jméno</th>
                    <th>Příjmení</th>
                    <th>Třída</th>
                    <th>Akce</th>
                </tr>
                </thead>
                <tbody>
                <?php
                try {
                    if ($search !== '') {
                        $stmt = $conn->prepare("SELECT * FROM zak_zakaznici WHERE jmeno LIKE ? OR prijmeni LIKE ? OR trida LIKE ? ORDER BY prijmeni, jmeno");
                        $stmt->execute([$searchParam, $searchParam, $searchParam]);
                    } else {
                        $stmt = $conn->query("SELECT * FROM zak_zakaznici ORDER BY prijmeni, jmeno");
                    }

                    if ($stmt->rowCount() === 0) {
                        echo "<tr><td colspan='5' style='text-align: center;'>Žádní žáci nenalezeni.</td></tr>";
                    } else {
                        while($row = $stmt->fetch()) {
                            echo "<td>" . htmlspecialchars($row['zakID']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['jmeno']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['prijmeni']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['trida']) . "</td>";
                            echo "<td class='action-buttons'>
                                    <form method='POST' style='display: inline;' onsubmit='return confirm(\"Opravdu smazat tohoto žáka?\")'>
                                        <input type='hidden' name='csrf_token' value='" . htmlspecialchars(generateCSRFToken()) . "'>
                                        <input type='hidden' name='zak_id' value='" . htmlspecialchars($row['zakID']) . "'>
                                        <button type='submit' name='smazat_zaka' class='btn-delete'>🗑️ Smazat</button>
                                    </form>
                                  </td>";
                            echo "</tr>";
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Chyba při načítání žáků: " . $e->getMessage());
                    echo "<tr><td colspan='5' style='text-align: center; color: red;'>Chyba při načítání dat.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </section>
    </main>

    <!-- Modal pro přidání žáka -->
    <div id="addStudentModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addStudentModal')">&times;</span>
            <h2>Přidat nového žáka</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <div class="form-group">
                    <label for="jmeno">Jméno:</label>
                    <input type="text" id="jmeno" name="jmeno" maxlength="50" required>
                </div>
                <div class="form-group">
                    <label for="prijmeni">Příjmení:</label>
                    <input type="text" id="prijmeni" name="prijmeni" maxlength="50" required>
                </div>
                <div class="form-group">
                    <label for="trida">Třída:</label>
                    <input type="text" id="trida" name="trida" maxlength="5" placeholder="např. 9.A" required>
                </div>
                <button type="submit" name="pridat_zaka" class="btn-primary">Přidat žáka</button>
            </form>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 Virtuální Knihovna | Školní knihovní systém</p>
    </footer>
</div>

<script>
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.style.display = "block";
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.style.display = "none";
    }

    window.onclick = function(event) {
        const modal = document.getElementById('addStudentModal');
        if (event.target === modal) modal.style.display = "none";
    }

    if (window.location.search.includes('success=') || window.location.search.includes('deleted=')) {
        setTimeout(function() {
            window.history.replaceState({}, document.title, window.location.pathname);
        }, 3000);
    }
</script>
</body>
</html>