<?php

class Growtype_Ai_Admin_Feed
{

    public function __construct()
    {
        add_action('admin_menu', array ($this, 'items_tab_init'));
    }

    /**
     * Create the All Users / Profile > Edit Profile and All Users Signups submenus.
     *
     * @since 2.0.0
     *
     */
    public function items_tab_init()
    {
        add_submenu_page(
            'growtype-ai',
            'Feed',
            'Feed',
            'manage_options',
            'growtype-ai-feed',
            array ($this, 'growtype_ai_result_callback'),
            100
        );
    }

    /**
     * Display callback for the submenu page.
     */
    function growtype_ai_result_callback()
    {
        $offset = isset($_GET['offset']) ? $_GET['offset'] : 0;
        $random = isset($_GET['random']) ? true : false;
        $limit = isset($_GET['limit']) ? $_GET['limit'] : 200;

        $query_args = [
            [
                'limit' => $limit,
                'offset' => $offset
            ]
        ];

        if ($random) {
            $query_args[0]['orderby'] = 'rand()';
        }

        $leonardo_ai_crud = new Leonardo_Ai_Feed();

        $offset = isset($_GET['offset']) ? $_GET['offset'] : 0;
        $search = isset($_GET['search']) ? $_GET['search'] : 'portrait';
        $model_id = isset($_GET['model_id']) ? $_GET['model_id'] : '';

        $feed = $leonardo_ai_crud->get_user_feed(1, [
            'search' => $search,
            'offset' => $offset,
            'model_id' => $model_id,
        ]);

        echo '<a href="https://growtype.com/wp/wp-admin/admin.php?page=growtype-ai-feed&offset=0&search=xxx">https://growtype.com/wp/wp-admin/admin.php?page=growtype-ai-feed&offset=0&search=xxx</a>';
        echo '<br>';
        echo '<br>';
        echo '<a href="https://app.leonardo.ai/models/b75a5b32-ca22-4b1d-bb0a-883c26783c71">https://app.leonardo.ai/models/b75a5b32-ca22-4b1d-bb0a-883c26783c71</a>';
        echo '<br>';
        echo '<br>';
        echo '<a href="https://growtype.com/wp/wp-admin/admin.php?page=growtype-ai-feed&offset=100&search=magic&model_id=ac614f96-1082-45bf-be9d-757f2d31c174">https://growtype.com/wp/wp-admin/admin.php?page=growtype-ai-feed&offset=100&search=magic&model_id=ac614f96-1082-45bf-be9d-757f2d31c174</a>';

        if (!empty($feed)) { ?>
            <div class="user-feed">
                <?php foreach ($feed as $image) {
//                    d($image);
                    ?>
                    <div class="user-feed-item">
                        <div class="img-wrapper">
                            <img src="<?php echo $image['url'] ?>" class="img-fluid" alt="">
                        </div>
                        <div class="details">
                            <div class="detail-single">
                                <b>Created At</b>
                                <p><?php echo $image['createdAt'] ?></p>
                            </div>
                            <div class="detail-single">
                                <b>Model Id</b>
                                <p><?php echo $image['generation']['modelId'] ?></p>
                            </div>
                            <div class="detail-single">
                                <b>Alchemy</b>
                                <p><?php echo isset($image['generation']['alchemy']) && !empty($image['generation']['alchemy']) ? 'true' : 'false' ?></p>
                            </div>
                            <div class="detail-single">
                                <b>Prompt</b>
                                <p><?php echo $image['generation']['prompt'] ?></p>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php }
    }
}


