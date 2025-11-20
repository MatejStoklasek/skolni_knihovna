<?php
session_start();
define('ACCESS_ALLOWED', true);
require_once 'config.php';

$conn = getDBConnection();
$error = '';
$success = '';

// Přidání nové knihy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pridat_knihu'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Neplatný bezpečnostní token!";
    } else {
        $nazev = sanitizeInput($_POST['nazev'] ?? '');
        $strany = filter_var($_POST['strany'] ?? '', FILTER_VALIDATE_INT);
        $zanr = sanitizeInput($_POST['zanr'] ?? '');
        $ks = filter_var($_POST['ks'] ?? '', FILTER_VALIDATE_INT);
        $vydavatelID = filter_var($_POST['vydavatelID'] ?? '', FILTER_VALIDATE_INT);
        $autorID = filter_var($_POST['autorID'] ?? '', FILTER_VALIDATE_INT);

        if (empty($nazev) || !$strany || !$ks || !$vydavatelID || !$autorID) {
            $error = "Všechna pole musí být vyplněna!";
        } elseif ($strany < 1 || $strany > 10000) {
            $error = "Počet stran musí být mezi 1 a 10000!";
        } elseif ($ks < 1) {
            $error = "Počet kusů musí být alespoň 1!";
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO kniha (nazev, strany, zanr, ks, vydavatelID, autorID) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nazev, $strany, $zanr, $ks, $vydavatelID, $autorID]);

                header("Location: knihy.php?success=1");
                exit();
            } catch (PDOException $e) {
                error_log("Chyba při přidání knihy: " . $e->getMessage());
                $error = "Chyba při přidávání knihy.";
            }
        }
    }
}

// Smazání knihy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['smazat_knihu'])) {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = "Neplatný bezpečnostní token!";
    } else {
        $knihaID = filter_var($_POST['kniha_id'] ?? '', FILTER_VALIDATE_INT);

        if ($knihaID) {
            try {
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM pujceni WHERE knihaID = ?");
                $stmt->execute([$knihaID]);
                $pujceni = $stmt->fetch()['count'];

                if ($pujceni > 0) {
                    $error = "Knihu nelze smazat, je momentálně půjčená!";
                } else {
                    $stmt = $conn->prepare("DELETE FROM kniha WHERE knihaID = ?");
                    $stmt->execute([$knihaID]);

                    header("Location: knihy.php?deleted=1");
                    exit();
                }
            } catch (PDOException $e) {
                error_log("Chyba při mazání knihy: " . $e->getMessage());
                $error = "Chyba při mazání knihy.";
            }
        }
    }
}

// Vyhledávání
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$searchParam = "%" . $search . "%";

if (isset($_GET['success'])) $success = "Kniha byla úspěšně přidána!";
if (isset($_GET['deleted'])) $success = "Kniha byla úspěšně smazána!";
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knihy - Virtuální Knihovna</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">
    <header>
        <h1>📚 Virtuální Knihovna</h1>
        <nav>
            <a href="main.php">Domů</a>
            <a href="knihy.php" class="active">Knihy</a>
            <a href="pujceni.php">Půjčení</a>
            <a href="zakaznici.php">Zákazníci</a>
            <a href="zamestnanci.php">Zaměstnanci</a>
        </nav>
    </header>

    <main>
        <section class="page-header">
            <h2>📖 Správa knih</h2>
            <p>Přehled všech knih v knihovně</p>
        </section>

        <?php if($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="action-bar">
            <form method="GET" class="search-form">
                <label for="search" class="sr-only">Hledat knihu</label>
                <input type="text" id="search" name="search" placeholder="Hledat knihu..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">🔍 Hledat</button>
            </form>
            <button type="button" onclick="openModal('addBookModal')" class="btn-primary">
                ➕ Přidat knihu
            </button>
        </div>

        <section class="content-section">
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Název</th>
                    <th>Autor</th>
                    <th>Vydavatel</th>
                    <th>Stran</th>
                    <th>Žánr</th>
                    <th>Kusů</th>
                    <th>Akce</th>
                </tr>
                </thead>
                <tbody>
                <?php
                try {
                    if ($search !== '') {
                        $stmt = $conn->prepare("SELECT k.*, CONCAT(a.jmeno, ' ', a.prijmeni) as autor_jmeno, v.nazev as vydavatel_nazev 
                                                FROM kniha k 
                                                JOIN autor a ON k.autorID = a.autorID 
                                                JOIN vydavatel v ON k.vydavatelID = v.vydavatelID
                                                WHERE k.nazev LIKE ? OR CONCAT(a.jmeno, ' ', a.prijmeni) LIKE ? OR k.zanr LIKE ? 
                                                ORDER BY k.nazev");
                        $stmt->execute([$searchParam, $searchParam, $searchParam]);
                    } else {
                        $stmt = $conn->query("SELECT k.*, CONCAT(a.jmeno, ' ', a.prijmeni) as autor_jmeno, v.nazev as vydavatel_nazev 
                                              FROM kniha k 
                                              JOIN autor a ON k.autorID = a.autorID 
                                              JOIN vydavatel v ON k.vydavatelID = v.vydavatelID
                                              ORDER BY k.nazev");
                    }

                    if ($stmt->rowCount() === 0) {
                        echo "<tr><td colspan='8' style='text-align: center;'>Žádné knihy nenalezeny.</td></tr>";
                    } else {
                        while($row = $stmt->fetch()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row['knihaID']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['nazev']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['autor_jmeno']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['vydavatel_nazev']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['strany']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['zanr']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['ks']) . "</td>";
                            echo "<td class='action-buttons'>
                                    <form method='POST' style='display: inline;' onsubmit='return confirm(\"Opravdu smazat tuto knihu?\")'>
                                        <input type='hidden' name='csrf_token' value='" . htmlspecialchars(generateCSRFToken()) . "'>
                                        <input type='hidden' name='kniha_id' value='" . htmlspecialchars($row['knihaID']) . "'>
                                        <button type='submit' name='smazat_knihu' class='btn-delete'>🗑️ Smazat</button>
                                    </form>
                                  </td>";
                            echo "</tr>";
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Chyba při načítání knih: " . $e->getMessage());
                    echo "<tr><td colspan='8' style='text-align: center; color: red;'>Chyba při načítání dat.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </section>
    </main>

    <!-- Modal pro přidání knihy -->
    <div id="addBookModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addBookModal')">&times;</span>
            <h2>Přidat novou knihu</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <div class="form-group">
                    <label for="nazev">Název knihy:</label>
                    <input type="text" id="nazev" name="nazev" maxlength="100" required>
                </div>
                <div class="form-group">
                    <label for="autorID">Autor:</label>
                    <select id="autorID" name="autorID" required>
                        <option value="">Vyberte autora...</option>
                        <?php
                        $autori = $conn->query("SELECT autorID, CONCAT(jmeno, ' ', prijmeni) as cele_jmeno FROM autor ORDER BY prijmeni, jmeno");
                        while($autor = $autori->fetch()) {
                            echo "<option value='" . $autor['autorID'] . "'>" . htmlspecialchars($autor['cele_jmeno']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="vydavatelID">Vydavatel:</label>
                    <select id="vydavatelID" name="vydavatelID" required>
                        <option value="">Vyberte vydavatele...</option>
                        <?php
                        $vydavatele = $conn->query("SELECT vydavatelID, nazev FROM vydavatel ORDER BY nazev");
                        while($vydavatel = $vydavatele->fetch()) {
                            echo "<option value='" . $vydavatel['vydavatelID'] . "'>" . htmlspecialchars($vydavatel['nazev']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="strany">Počet stran:</label>
                    <input type="number" id="strany" name="strany" min="1" max="10000" required>
                </div>
                <div class="form-group">
                    <label for="zanr">Žánr:</label>
                    <input type="text" id="zanr" name="zanr" maxlength="50" required>
                </div>
                <div class="form-group">
                    <label for="ks">Počet kusů:</label>
                    <input type="number" id="ks" name="ks" min="1" max="1000" value="1" required>
                </div>
                <button type="submit" name="pridat_knihu" class="btn-primary">Přidat knihu</button>
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
        const modal = document.getElementById('addBookModal');
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