[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bgithub.com%2FP4BGroup%2Fphalcon-nested-sets.svg?type=shield)](https://app.fossa.io/projects/git%2Bgithub.com%2FP4BGroup%2Fphalcon-nested-sets?ref=badge_shield)

Nested Set Behaviour
--- 

Phalcon implementation for tree / hierarchy through nested sets implementation.
It will calculate edges and depth for a category on creation, update and delete.

Moving "branches" is not supported (o sub-node with all it's sub-nodes) and won't be. This tool is meant to "react" to changes on a single record and keep the rest of the tree position in place.

prerequisites
---
Your DB must have parent, left, right, depth columns. 
Example:

```SQL
CREATE TABLE `categories` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`name` CHAR(50) NULL DEFAULT NULL,
	`parent_id` INT(11) NULL DEFAULT NULL,
	`_left` INT(11) NULL DEFAULT NULL,
	`_right` INT(11) NULL DEFAULT NULL,
	`_depth` INT(11) NULL DEFAULT NULL,
	PRIMARY KEY (`id`)
);
```

usage
---

```php

class MyModel extends \Phalcon\Mvc\Model {
    public function initialize() {
        $this->addBehaviour(new \P4BGroup\NestedSets\Behaviour());
    }
}

```

references
---
* https://en.wikipedia.org/wiki/Nested_set_model
* https://explainextended.com/2009/09/29/adjacency-list-vs-nested-sets-mysql/
* https://medium.com/@Sumurai8/nested-sets-performant-attribute-calculation-on-collections-10289a30c0ab

usage
---
this behaviour will automatically calculate the edges and depth of each node on save / delete

Similar implementations on other frameworks
---
* https://github.com/etrepat/baum - laravel 
* https://github.com/lazychaser/laravel-nestedset - laravel 
* https://github.com/blt04/doctrine2-nestedset - doctrine
* https://github.com/bartko-s/stefano-tree - zend / pdo
* https://github.com/creocoder/yii2-nested-sets - yii


## License
[![FOSSA Status](https://app.fossa.io/api/projects/git%2Bgithub.com%2FP4BGroup%2Fphalcon-nested-sets.svg?type=large)](https://app.fossa.io/projects/git%2Bgithub.com%2FP4BGroup%2Fphalcon-nested-sets?ref=badge_large)