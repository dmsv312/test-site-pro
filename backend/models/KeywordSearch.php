<?php

declare(strict_types=1);

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\Expression;

/**
 * Search/filter model behind the admin keywords grid.
 */
class KeywordSearch extends Keyword
{
    /** Virtual filter: minimum average monthly searches. */
    public int|string|null $minVolume = null;

    public function rules(): array
    {
        return [
            [['source', 'language', 'stage', 'raw_term', 'competition', 'competitor_domain', 'drop_reason'], 'safe'],
            [['batch_id', 'minVolume'], 'integer'],
        ];
    }

    /** Bypass the parent's required-field scenario; all filter fields are safe. */
    public function scenarios(): array
    {
        return Model::scenarios();
    }

    public function search(array $params): ActiveDataProvider
    {
        // Default order: highest volume first, with the metric-less rows (Search Console) last.
        // Postgres sorts NULLs first on DESC, so we spell out NULLS LAST. Clicking a column
        // header replaces this with that column's plain sort.
        $query = Keyword::find()->orderBy(new Expression('avg_monthly_searches DESC NULLS LAST, id ASC'));

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 50],
            'sort' => [
                'attributes' => [
                    'id', 'source', 'raw_term', 'normalized_term', 'language',
                    'avg_monthly_searches', 'cpc', 'competition', 'competitor_domain',
                    'stage', 'batch_id',
                ],
            ],
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere([
            'source' => $this->source ?: null,
            'language' => $this->language ?: null,
            'stage' => $this->stage ?: null,
            'competition' => $this->competition ?: null,
            'batch_id' => $this->batch_id ?: null,
        ]);
        $query->andFilterWhere(['ilike', 'raw_term', $this->raw_term]);
        $query->andFilterWhere(['ilike', 'competitor_domain', $this->competitor_domain]);
        $query->andFilterWhere(['ilike', 'drop_reason', $this->drop_reason]);

        if ($this->minVolume !== null && $this->minVolume !== '') {
            $query->andWhere(['>=', 'avg_monthly_searches', (int) $this->minVolume]);
        }

        return $dataProvider;
    }
}
