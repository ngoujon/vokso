import { cleanTitle, episodePath, slugify } from './text';

test('nettoie le Markdown des titres', () => {
  expect(cleanTitle('**Les volcans**  #actifs')).toBe('Les volcans actifs');
  expect(cleanTitle(null)).toBe('');
});

test('produit le même slug que l\'API', () => {
  expect(slugify('Été à Paris : l’histoire !')).toBe('ete-a-paris-l-histoire');
  expect(episodePath('gen_1', '**Été**')).toBe('/podcast/gen_1-ete');
  expect(episodePath('gen_2', '')).toBe('/podcast/gen_2');
  expect(episodePath('gen_3', 'Les volcans', 'les-volcans')).toBe('/podcast/les-volcans');
});
