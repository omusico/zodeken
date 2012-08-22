<?php

return <<<CODE
<?php if (\$this->pageCount): ?>
<div class="pagination">
<ul>
<!-- Previous page link -->
<?php if (isset(\$this->previous)): ?>
  <li><a href="<?php echo \$this->url(\$_GET + array('page' => \$this->previous)); ?>">
    «
  </a></li>
<?php else: ?>
  <li class="disabled"><a>«</a></li>
<?php endif; ?>
 
<!-- Numbered page links -->
<?php foreach (\$this->pagesInRange as \$page): ?>
  <?php if (\$page != \$this->current): ?>
   <li>
    <a href="<?php echo \$this->url(\$_GET + array('page' => \$page)); ?>">
        <?php echo \$page; ?>
    </a></li>
  <?php else: ?>
    <li class="active"><a><?php echo \$page; ?></a></li>
  <?php endif; ?>
<?php endforeach; ?>
 
<!-- Next page link -->
<?php if (isset(\$this->next)): ?>
  <li><a href="<?php echo \$this->url(\$_GET + array('page' => \$this->next)); ?>">
    »
  </a></li>
<?php else: ?>
   <li class="disabled"><a>»</a></li>
<?php endif; ?>
</ul>
</div>
<?php endif; ?>
CODE;
?>
