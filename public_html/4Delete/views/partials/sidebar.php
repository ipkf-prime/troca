<ul class="sidebar-menu">

    <?php foreach ($sidebarMenus as $menu): ?>

        <li>
            <a href="<?= $menu['link'] ?>">
                <i class="<?= $menu['icon'] ?>"></i>
                <?= htmlspecialchars($menu['title']) ?>
            </a>

            <?php if (!empty($menu['children'])): ?>
                <ul class="submenu">
                    <?php foreach ($menu['children'] as $child): ?>
                        <li>
                            <a href="<?= $child['link'] ?>">
                                <?= htmlspecialchars($child['title']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

        </li>

    <?php endforeach; ?>

</ul>