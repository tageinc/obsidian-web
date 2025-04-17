private function linspace(float $x0, float $xf, int $n)
{
	$delta = ($xf - $x0) / (float) $n;
	$x = [$x0];
	for ($i = 0; $i < $n; $i++) {
		$x_i = $x[$i] + $delta;
		array_push($x, $x_i);
	}
	return $x;
}

private function array1D(float $x0, float $delta, int $n)
{
	$x = [$x0];
	for ($i = 0; $i < $n; $i++) {
		$x_i = $x[$i] + $delta;
		array_push($x, $x_i);
	}
	return $x;
}