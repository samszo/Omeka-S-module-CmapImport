SET FOREIGN_KEY_CHECKS=0;
ALTER TABLE cmap_import DROP FOREIGN KEY FK_82A3EEB8BE04E_CMAP;
ALTER TABLE cmap_import DROP FOREIGN KEY FK_82A3EEB84C27_CMAP;
ALTER TABLE cmap_import_item DROP FOREIGN KEY FK_86A2392BB6A2_DCMAP;
ALTER TABLE cmap_import_item DROP FOREIGN KEY FK_86A2392B126F_CMAP;

DROP TABLE cmap_import;
DROP TABLE cmap_import_item;
SET FOREIGN_KEY_CHECKS=1;
