<?php
require_once 'includes/config.php';
checkAuth();

$pageTitle = 'Posts e Notícias';

$initialPosts = [
    [
        'id' => 1,
        'title' => 'Novo Post FSA',
        'content' => 'Confira as novidades da Fundação Santo André para o semestre 2026.2',
        'hashtags' => ['FSA', 'Estágio', 'Oportunidades'],
        'images' => ['https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=1600&q=100'],
        'whiteBackground' => false,
        'createdAt' => '20/03/2026',
    ],
];

$postsFile = 'data/platform_posts.json';
$posts = file_exists($postsFile) ? json_decode(file_get_contents($postsFile), true) : $initialPosts;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $newPost = [
            'id' => time(),
            'title' => $_POST['title'] ?? '',
            'content' => $_POST['content'] ?? '',
            'hashtags' => array_filter(array_map('trim', explode(',', $_POST['hashtags'] ?? ''))),
            'images' => [],
            'whiteBackground' => isset($_POST['whiteBackground']),
            'createdAt' => date('d/m/Y'),
        ];

        if (!empty($_FILES['images']['name'][0])) {
            $uploadDir = 'uploads/posts/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                $fileName = time() . '_' . $_FILES['images']['name'][$key];
                $uploadPath = $uploadDir . $fileName;
                if (move_uploaded_file($tmp_name, $uploadPath)) {
                    $newPost['images'][] = $uploadPath;
                }
            }
        }

        array_unshift($posts, $newPost);
        saveData('platform_posts', $posts);
        header('Location: posts.php');
        exit;
    }

    if ($action === 'edit') {
        $id = $_POST['id'] ?? 0;
        foreach ($posts as &$post) {
            if ($post['id'] == $id) {
                $post['title']           = $_POST['title'] ?? $post['title'];
                $post['content']         = $_POST['content'] ?? $post['content'];
                $post['hashtags']        = array_filter(array_map('trim', explode(',', $_POST['hashtags'] ?? '')));
                $post['whiteBackground'] = isset($_POST['whiteBackground']);

                if (!empty($_FILES['images']['name'][0])) {
                    $uploadDir = 'uploads/posts/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    $post['images'] = [];
                    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                        $fileName = time() . '_' . $_FILES['images']['name'][$key];
                        $uploadPath = $uploadDir . $fileName;
                        if (move_uploaded_file($tmp_name, $uploadPath)) {
                            $post['images'][] = $uploadPath;
                        }
                    }
                }
                break;
            }
        }
        saveData('platform_posts', $posts);
        header('Location: posts.php');
        exit;
    }

    if ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        $posts = array_filter($posts, function($post) use ($id) {
            return $post['id'] != $id;
        });
        saveData('platform_posts', $posts);
        header('Location: posts.php');
        exit;
    }
}

include 'includes/header.php';
?>

<main class="ml-16 pt-16">
    <div class="p-8 max-w-7xl mx-auto">

        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Posts e Notícias</h2>
                <p class="text-gray-600">Gerencie o que aparece no Dashboard dos alunos</p>
            </div>
            <button onclick="openCreateModal()" class="px-4 py-2 bg-[#4A9FCA] text-white rounded-lg hover:bg-[#3A8FB0] transition-all">
                + Novo Post
            </button>
        </div>

        <!-- Modal Criar Post -->
        <div id="postModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
            <div class="bg-white rounded-[32px] p-10 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <h3 class="text-2xl font-bold text-gray-900 mb-6">Criar Nova Postagem</h3>
                <form method="POST" action="posts.php" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="action" value="save">

                    <div>
                        <label class="block text-sm font-bold text-gray-900 mb-2">Título do Post</label>
                        <input type="text" name="title" class="w-full h-12 px-6 bg-gray-50 rounded-2xl" placeholder="Ex: Evento de Tecnologia 2026" required>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-900 mb-2">Imagens</label>
                        <div class="border-2 border-dashed border-gray-200 rounded-[32px] p-10 text-center bg-white">
                            <i class="fas fa-upload text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500 mb-6">Selecione imagens para o seu post</p>
                            <input type="file" name="images[]" multiple accept="image/*"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-900 mb-2">Conteúdo do Post</label>
                        <textarea name="content" rows="5" class="w-full p-6 bg-gray-50 rounded-2xl resize-none" placeholder="Escreva aqui os detalhes da notícia..." required></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-900 mb-2">Hashtags (separadas por vírgula)</label>
                        <input type="text" name="hashtags" class="w-full h-12 px-6 bg-gray-50 rounded-2xl" placeholder="FSA, Estágio, Oportunidades">
                    </div>

                    <div class="flex items-center justify-between p-6 bg-gray-50 rounded-[24px]">
                        <div>
                            <label class="text-lg font-bold text-gray-900">Remover fundo branco?</label>
                            <p class="text-sm text-gray-500">Aplica transparência automática</p>
                        </div>
                        <input type="checkbox" name="whiteBackground" class="w-6 h-6">
                    </div>

                    <div class="flex gap-4 pt-4">
                        <button type="button" onclick="closeModal()" class="flex-1 h-14 border border-gray-200 rounded-2xl font-bold">Cancelar</button>
                        <button type="submit" class="flex-1 h-14 bg-[#4A9FCA] text-white rounded-2xl font-bold">Publicar Agora</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Editar Post -->
        <div id="editModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
            <div class="bg-white rounded-[32px] p-10 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <h3 class="text-2xl font-bold text-gray-900 mb-6">Editar Postagem</h3>
                <form method="POST" action="posts.php" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="editPostId">

                    <div>
                        <label class="block text-sm font-bold text-gray-900 mb-2">Título do Post</label>
                        <input type="text" name="title" id="editPostTitle" class="w-full h-12 px-6 bg-gray-50 rounded-2xl" required>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-900 mb-2">Nova Imagem (opcional)</label>
                        <div class="border-2 border-dashed border-gray-200 rounded-[32px] p-8 text-center bg-white">
                            <p class="text-gray-400 text-sm mb-3">Deixe vazio para manter a imagem atual</p>
                            <div id="editCurrentImage" class="mb-4"></div>
                            <input type="file" name="images[]" multiple accept="image/*"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-900 mb-2">Conteúdo do Post</label>
                        <textarea name="content" id="editPostContent" rows="5" class="w-full p-6 bg-gray-50 rounded-2xl resize-none" required></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-900 mb-2">Hashtags (separadas por vírgula)</label>
                        <input type="text" name="hashtags" id="editPostHashtags" class="w-full h-12 px-6 bg-gray-50 rounded-2xl">
                    </div>

                    <div class="flex items-center justify-between p-6 bg-gray-50 rounded-[24px]">
                        <div>
                            <label class="text-lg font-bold text-gray-900">Remover fundo branco?</label>
                            <p class="text-sm text-gray-500">Aplica transparência automática</p>
                        </div>
                        <input type="checkbox" name="whiteBackground" id="editPostWhiteBg" class="w-6 h-6">
                    </div>

                    <div class="flex gap-4 pt-4">
                        <button type="button" onclick="closeEditModal()" class="flex-1 h-14 border border-gray-200 rounded-2xl font-bold">Cancelar</button>
                        <button type="submit" class="flex-1 h-14 bg-[#4A9FCA] text-white rounded-2xl font-bold">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Grid de Posts -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($posts as $post): ?>
                <div class="bg-white rounded-[32px] overflow-hidden shadow-sm hover:shadow-2xl transition-all">
                    <div class="bg-gradient-to-br from-[#4A9FCA] to-[#2B7FA6] p-6 text-white">
                        <h3 class="font-bold text-xl mb-1 truncate"><?php echo htmlspecialchars($post['title']); ?></h3>
                        <p class="text-xs opacity-70">Publicado em <?php echo $post['createdAt']; ?></p>
                    </div>

                    <div class="p-6">
                        <?php if (!empty($post['images'])): ?>
                            <div class="grid grid-cols-3 gap-2 mb-6">
                                <?php foreach (array_slice($post['images'], 0, 3) as $image): ?>
                                    <img src="<?php echo htmlspecialchars($image); ?>" alt="Post"
                                        class="w-full h-24 object-cover rounded-2xl shadow-sm"
                                        onerror="this.style.display='none'">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <p class="text-gray-600 text-sm mb-6 line-clamp-3 leading-relaxed">
                            <?php echo htmlspecialchars(substr($post['content'], 0, 150)); ?>...
                        </p>

                        <div class="flex flex-wrap gap-2 mb-6">
                            <?php foreach ($post['hashtags'] as $tag): ?>
                                <span class="text-[#4A9FCA] text-xs font-bold bg-blue-50 px-3 py-1.5 rounded-xl">
                                    #<?php echo htmlspecialchars(trim($tag)); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>

                        <!-- ✅ Botões estilo documents.php -->
                        <div class="flex gap-3 pt-6 border-t border-gray-100">

                            <!-- Editar -->
                            <button
                                onclick='editPost(
                                    <?php echo $post["id"]; ?>,
                                    <?php echo json_encode($post["title"]); ?>,
                                    <?php echo json_encode($post["content"]); ?>,
                                    <?php echo json_encode(implode(", ", $post["hashtags"])); ?>,
                                    <?php echo $post["whiteBackground"] ? "true" : "false"; ?>,
                                    <?php echo json_encode($post["images"][0] ?? ""); ?>
                                )'
                                class="flex-1 px-3 py-2 bg-[#4A9FCA] hover:bg-[#3A8FB0] text-white rounded-xl text-sm font-semibold transition-all flex items-center justify-center gap-2">
                                <i class="fas fa-edit"></i> Editar
                            </button>

                            <!-- Excluir -->
                            <button type="button"
                                onclick="abrirModalExcluir(<?php echo $post['id']; ?>, '<?php echo htmlspecialchars($post['title'], ENT_QUOTES); ?>')"
                                class="flex-1 px-3 py-2 border border-red-200 text-red-600 rounded-xl text-sm font-semibold hover:bg-red-50 transition-all flex items-center justify-center gap-2">
                                <i class="fas fa-trash-alt"></i> Excluir
                            </button>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($posts)): ?>
                <div class="col-span-full p-24 text-center border-2 border-dashed border-gray-200 bg-white rounded-[40px]">
                    <i class="fas fa-image text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">Sua galeria está vazia</h3>
                    <p class="text-gray-500 mb-10">Crie postagens para manter os alunos informados.</p>
                    <button onclick="openCreateModal()" class="px-10 py-4 bg-[#4A9FCA] text-white rounded-2xl font-bold text-lg">
                        + Criar Primeiro Post
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Form oculto de exclusão -->
        <form id="formExcluir" method="POST" action="posts.php">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="excluirId" value="">
        </form>

        <!-- ✅ Modal Excluir — mesmo estilo do documents.php -->
        <div id="modalExcluir" class="fixed inset-0 z-[999] hidden items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="fecharModalExcluir()"></div>
            <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-sm p-8 z-10">

                <div class="w-16 h-16 bg-red-100 rounded-2xl flex items-center justify-center mx-auto mb-5">
                    <i class="fas fa-trash-alt text-red-500 text-3xl"></i>
                </div>

                <h3 class="text-xl font-bold text-gray-900 text-center mb-2">Excluir Post?</h3>
                <p class="text-gray-500 text-sm text-center mb-1 leading-relaxed">Você está prestes a excluir:</p>
                <p id="modalNomePost" class="text-[#4A9FCA] font-semibold text-sm text-center mb-3 leading-relaxed"></p>
                <p class="text-gray-400 text-xs text-center mb-8">Esta ação não pode ser desfeita e o post sumirá para todos os alunos.</p>

                <div class="flex gap-3">
                    <button onclick="fecharModalExcluir()"
                            class="flex-1 py-3 px-4 rounded-2xl border-2 border-gray-200 text-gray-600 font-semibold hover:bg-gray-50 transition-all text-sm">
                        <i class="fas fa-times mr-1"></i> Cancelar
                    </button>
                    <button onclick="confirmarExcluir()"
                            class="flex-1 py-3 px-4 rounded-2xl bg-red-500 hover:bg-red-600 text-white font-semibold transition-all text-sm shadow-lg shadow-red-100">
                        <i class="fas fa-trash-alt mr-1"></i> Excluir
                    </button>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
    // =============================
    // Modal Criar
    // =============================
    function openCreateModal() {
        document.getElementById('postModal').classList.remove('hidden');
    }
    function closeModal() {
        document.getElementById('postModal').classList.add('hidden');
    }

    // =============================
    // Modal Editar
    // =============================
    function editPost(id, title, content, hashtags, whiteBg, currentImage) {
        document.getElementById('editPostId').value       = id;
        document.getElementById('editPostTitle').value    = title;
        document.getElementById('editPostContent').value  = content;
        document.getElementById('editPostHashtags').value = hashtags;
        document.getElementById('editPostWhiteBg').checked = whiteBg;

        const imgContainer = document.getElementById('editCurrentImage');
        if (currentImage) {
            imgContainer.innerHTML = `
                <p class="text-xs text-gray-400 mb-2">Imagem atual:</p>
                <img src="${currentImage}" alt="Imagem atual" class="h-24 rounded-xl object-cover mx-auto" onerror="this.parentElement.style.display='none'">
            `;
        } else {
            imgContainer.innerHTML = '<p class="text-xs text-gray-400">Nenhuma imagem atual</p>';
        }

        document.getElementById('editModal').classList.remove('hidden');
    }
    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }

    // =============================
    // Modal Excluir
    // =============================
    function abrirModalExcluir(id, nome) {
        document.getElementById('excluirId').value          = id;
        document.getElementById('modalNomePost').textContent = nome;
        const modal = document.getElementById('modalExcluir');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    function fecharModalExcluir() {
        const modal = document.getElementById('modalExcluir');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    function confirmarExcluir() {
        document.getElementById('formExcluir').submit();
    }

    // =============================
    // Fechar modais clicando fora / ESC
    // =============================
    document.getElementById('postModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeModal();
            closeEditModal();
            fecharModalExcluir();
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
