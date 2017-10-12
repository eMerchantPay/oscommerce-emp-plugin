<?php
/*
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NON-INFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @license     http://opensource.org/licenses/MIT The MIT License
 */

namespace Genesis\API\Traits\Request\Financial;

/**
 * Trait MpiAttributes
 * @package Genesis\API\Traits\Request\Financial
 *
 * @method $this setMpiCavv($value) Set the Verification Id of the authentication.
 * @method $this setMpiEci($value) Set Electric Commerce Indicator as returned from the MPI.
 * @method $this setMpiXid($value) Set Transaction ID that uniquely identifies a 3D Secure check request
 */
trait MpiAttributes
{
    /**
     * Verification Id of the authentication.
     *
     * Please note this can be the CAVV for Visa Card or UCAF to identify MasterCard.
     *
     * @var string
     */
    protected $mpi_cavv;

    /**
     * Electric Commerce Indicator as returned from the MPI.
     *
     * @var string
     */
    protected $mpi_eci;

    /**
     * Transaction ID generated by the 3D Secure service
     * that uniquely identifies a 3D Secure check request
     *
     * @var string
     */
    protected $mpi_xid;

    /**
     * Builds an array list with all Params
     *
     * @return array
     */
    protected function getMpiParamsStructure()
    {
        return [
            'cavv' => $this->mpi_cavv,
            'eci'  => $this->mpi_eci,
            'xid'  => $this->mpi_xid,
        ];
    }
}
