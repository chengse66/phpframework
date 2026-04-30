<?php if(!defined("ALLOW_ACCESS")) exit("not access");?><div class="test-module">
    <div class="module-header">Template Engine</div>
    <?php  foreach ( $tests as $test) { ?> 
    <div class="test-row">
        <span class="badge <?php  echo htmlspecialchars($test["badge"], ENT_QUOTES, 'UTF-8');  ?>"></span>
        <span><?php  echo htmlspecialchars($test["name"], ENT_QUOTES, 'UTF-8');  ?></span>
        <span class="detail"><?php  echo htmlspecialchars($test["detail"], ENT_QUOTES, 'UTF-8');  ?></span>
    </div>
    <?php  }  ?>
</div>