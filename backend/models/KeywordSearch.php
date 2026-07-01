<?php

declare(strict_types=1);

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use yii\db\Expression;

/**
 * Search/filter model behind the admin keywords grid.
 *
 * The prominent control is a **pipeline view** ({@see effectiveView()}): All · Cleaned · Prepared ·
 * Dropped. Each view is a stage-aware base scope that the column filters (source, language, volume,
 * competition, drop reason, ad group) narrow further. The views are lenses on one table, not a
 * partition — a keyword that survived cleaning but was dropped in preparation shows under both
 * Cleaned and Dropped — so each view answers one clear question and the counts match the pipeline
 * pages ({@see counts()} feeds the tab badges, which reconcile with the Cleaning/Prepare funnels).
 */
class KeywordSearch extends Keyword
{
    /** Virtual filter: minimum average monthly searches. */
    public int|string|null $minVolume = null;

    /** Virtual filter: which pipeline view to show (see {@see effectiveView()}). */
    public ?string $view = null;

    public const VIEW_ALL = 'all';
    public const VIEW_CLEANED = 'cleaned';       // survived cleaning — the ad-candidate set
    public const VIEW_PREPARED = 'prepared';     // net-new, campaign-ready
    public const VIEW_DROPPED = 'dropped';       // flagged by any stage, with a reason

    public const VIEWS = [self::VIEW_ALL, self::VIEW_CLEANED, self::VIEW_PREPARED, self::VIEW_DROPPED];

    private ?string $resolvedView = null;

    public function rules(): array
    {
        return [
            [['source', 'language', 'stage', 'raw_term', 'competition', 'competitor_domain', 'drop_reason', 'view'], 'safe'],
            [['batch_id', 'minVolume', 'ad_group_id'], 'integer'],
        ];
    }

    /**
     * The active view, defaulting to the ad-candidate set once cleaning has run (so dropped junk
     * doesn't clutter the default) and to everything before that (so a fresh import is never a blank
     * page). An explicit, valid `view` param always wins. Resolved once per request.
     */
    public function effectiveView(): string
    {
        if ($this->resolvedView !== null) {
            return $this->resolvedView;
        }
        if (in_array($this->view, self::VIEWS, true)) {
            return $this->resolvedView = $this->view;
        }

        $cleaningHasRun = Keyword::find()->where(['<>', 'stage', Keyword::STAGE_IMPORTED])->exists();

        return $this->resolvedView = $cleaningHasRun ? self::VIEW_CLEANED : self::VIEW_ALL;
    }

    /** Bypass the parent's required-field scenario; all filter fields are safe. */
    public function scenarios(): array
    {
        return Model::scenarios();
    }

    /** Row counts per view, for the tab badges (and cross-page reconciliation). */
    public static function counts(): array
    {
        $count = static fn(array $condition): int => (int) Keyword::find()->where($condition)->count();

        return [
            self::VIEW_ALL => (int) Keyword::find()->count(),
            self::VIEW_CLEANED => $count(['<>', 'stage', Keyword::STAGE_IMPORTED]),
            self::VIEW_PREPARED => $count(['stage' => Keyword::STAGE_PREPARED]),
            self::VIEW_DROPPED => $count(['not', ['drop_reason' => null]]),
        ];
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
            'ad_group_id' => $this->ad_group_id ?: null,
        ]);
        $query->andFilterWhere(['ilike', 'raw_term', $this->raw_term]);
        $query->andFilterWhere(['ilike', 'competitor_domain', $this->competitor_domain]);
        $query->andFilterWhere(['ilike', 'drop_reason', $this->drop_reason]);

        if ($this->minVolume !== null && $this->minVolume !== '') {
            $query->andWhere(['>=', 'avg_monthly_searches', (int) $this->minVolume]);
        }

        // Pipeline view (the prominent tabs), applied as a base scope on top of the column filters.
        // Cleaned = survived cleaning (past import); Prepared = the net-new set; Dropped = has a
        // reason from any stage; All = no scope.
        switch ($this->effectiveView()) {
            case self::VIEW_CLEANED:
                $query->andWhere(['<>', 'stage', Keyword::STAGE_IMPORTED]);
                break;
            case self::VIEW_PREPARED:
                $query->andWhere(['stage' => Keyword::STAGE_PREPARED]);
                break;
            case self::VIEW_DROPPED:
                $query->andWhere(['not', ['drop_reason' => null]]);
                break;
            // VIEW_ALL: no base scope.
        }

        return $dataProvider;
    }
}
